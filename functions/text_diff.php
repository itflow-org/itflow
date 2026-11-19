<?php

/*
 * ITFlow - what changed between two versions of a document, as text (the Changes window,
 * agent/modals/document/document_changes.php).
 *
 * Both are turned into lines of text - a paragraph, a heading, a list item, a table row - and
 * compared line by line: a line taken out, a line put in, or a line changed, where the words that
 * changed are marked. Lines that didn't change are folded away, but for two either side of each
 * change. Formatting alone (bold, a colour) isn't a change here; a picture shows as [Picture].
 */

/** HTML as the lines the comparison works on. */
function textDiffLines($html) {
    $html = preg_replace('#<img\b[^>]*>#i', "\n[Picture]\n", (string) $html);
    $html = preg_replace('#</(td|th)\s*>#i', ' | ', $html);
    $lines = [];
    foreach (explode("\n", emailPlainText($html)) as $line) {
        $line = trim(preg_replace('/\s*\|\s*$/u', '', $line) ?? $line);
        if ($line !== '') {
            $lines[] = $line;
        }
    }
    return $lines;
}

/**
 * The longest common subsequence of two lists, as operations: ['=', item] kept, ['-', item] taken
 * out, ['+', item] put in. What is the same at the start and the end is matched first, so a long
 * document with a small edit compares quickly. Past $limit cells the middle is shown as all taken
 * out then all put in, rather than spending the memory.
 */
function textDiffOps(array $a, array $b, $limit = 250000) {
    $head = [];
    while ($a && $b && $a[0] === $b[0]) {
        $head[] = ['=', array_shift($a)];
        array_shift($b);
    }
    $tail = [];
    while ($a && $b && end($a) === end($b)) {
        array_unshift($tail, ['=', array_pop($a)]);
        array_pop($b);
    }

    $n = count($a);
    $m = count($b);
    $middle = [];
    if ($n * $m > $limit) {
        foreach ($a as $item) {
            $middle[] = ['-', $item];
        }
        foreach ($b as $item) {
            $middle[] = ['+', $item];
        }
    } elseif ($n || $m) {
        // Lengths of the common subsequence from each point on
        $len = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $len[$i][$j] = $a[$i] === $b[$j] ? $len[$i + 1][$j + 1] + 1 : max($len[$i + 1][$j], $len[$i][$j + 1]);
            }
        }
        $i = $j = 0;
        while ($i < $n || $j < $m) {
            if ($i < $n && $j < $m && $a[$i] === $b[$j]) {
                $middle[] = ['=', $a[$i++]];
                $j++;
            } elseif ($i < $n && ($j >= $m || $len[$i + 1][$j] >= $len[$i][$j + 1])) {
                // Taken out before put in, so a changed line reads as one
                $middle[] = ['-', $a[$i++]];
            } else {
                $middle[] = ['+', $b[$j++]];
            }
        }
    }
    return array_merge($head, $middle, $tail);
}

/** One changed line with the words that changed marked - escaped HTML for the old and the new. */
function textDiffWordsHtml($old, $new) {
    $split = function ($text) {
        return preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
    };
    $ops = textDiffOps($split($old), $split($new), 40000);
    $before = $after = '';
    foreach ($ops as [$op, $word]) {
        $word = escapeHtml($word);
        if ($op === '=') {
            $before .= $word;
            $after .= $word;
        } elseif ($op === '-') {
            $before .= "<del>$word</del>";
        } else {
            $after .= "<ins>$word</ins>";
        }
    }
    return [$before, $after];
}

/**
 * The changes from $old_html to $new_html as HTML for the Changes window - '' when the text is the
 * same. A line taken out and one put in at the same place are shown as that line changed.
 */
function textDiffHtml($old_html, $new_html, $context = 2) {
    $ops = textDiffOps(textDiffLines($old_html), textDiffLines($new_html));
    if (!array_filter($ops, function ($op) { return $op[0] !== '='; })) {
        return '';
    }

    // In each stretch of changes, a line taken out and a line put in that are mostly the same
    // words are one line changed - shown together, with the words that changed marked
    $rows = [];
    for ($k = 0, $count = count($ops); $k < $count;) {
        if ($ops[$k][0] === '=') {
            $rows[] = ['same', escapeHtml($ops[$k][1])];
            $k++;
            continue;
        }
        $removed = $added = [];
        while ($k < $count && $ops[$k][0] !== '=') {
            if ($ops[$k][0] === '-') {
                $removed[] = $ops[$k][1];
            } else {
                $added[] = $ops[$k][1];
            }
            $k++;
        }
        $next = 0;
        foreach ($removed as $line) {
            $match = -1;
            for ($a = $next, $added_count = count($added); $a < $added_count; $a++) {
                similar_text(mb_strtolower(mb_substr($line, 0, 300)), mb_strtolower(mb_substr($added[$a], 0, 300)), $percent);
                if ($percent >= 50) {
                    $match = $a;
                    break;
                }
            }
            if ($match < 0) {
                $rows[] = ['del', '<del>' . escapeHtml($line) . '</del>'];
                continue;
            }
            for (; $next < $match; $next++) {
                $rows[] = ['add', '<ins>' . escapeHtml($added[$next]) . '</ins>'];
            }
            [$before, $after] = textDiffWordsHtml($line, $added[$match]);
            $rows[] = ['del', $before];
            $rows[] = ['add', $after];
            $next = $match + 1;
        }
        for ($added_count = count($added); $next < $added_count; $next++) {
            $rows[] = ['add', '<ins>' . escapeHtml($added[$next]) . '</ins>'];
        }
    }

    // Unchanged lines more than $context away from a change fold into "N lines the same"
    $near = [];
    foreach ($rows as $r => $row) {
        if ($row[0] !== 'same') {
            for ($c = max(0, $r - $context); $c <= min(count($rows) - 1, $r + $context); $c++) {
                $near[$c] = true;
            }
        }
    }
    $html = '';
    $folded = 0;
    $marks = ['same' => '', 'del' => '−', 'add' => '+'];
    foreach ($rows as $r => $row) {
        if ($row[0] === 'same' && !isset($near[$r])) {
            $folded++;
            continue;
        }
        if ($folded) {
            $html .= '<div class="itflow-diff-fold">' . $folded . ' line' . ($folded === 1 ? '' : 's') . ' the same</div>';
            $folded = 0;
        }
        $html .= '<div class="itflow-diff-line is-' . $row[0] . '"><span class="itflow-diff-mark" aria-hidden="true">' . $marks[$row[0]] . '</span><span>' . $row[1] . '</span></div>';
    }
    if ($folded) {
        $html .= '<div class="itflow-diff-fold">' . $folded . ' line' . ($folded === 1 ? '' : 's') . ' the same</div>';
    }
    return $html;
}
