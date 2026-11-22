/*
 * Rack page (agent/rack.php) - drag kit into place on the elevation.
 *
 * An item on the rack drags to another position, or across to the other face,
 * and is moved there when dropped (move_rack_unit in agent/post/rack.php,
 * through #rackMoveForm). An asset from Not racked yet drags onto the rack and
 * opens Add with the asset and where it was dropped filled in, so its height
 * and depth can be checked before it is saved. While dragging, the rack shows
 * where it would go - red where something is in the way. The server checks
 * again, so this is only a guide.
 *
 * The data-rack-* attributes come from agent/includes/rack_elevation.php:
 * each bay knows its face, height and numbering; each item its unit, units
 * and depth. Click still does what it did - an empty U adds, an item opens.
 *
 * Holders: an empty slot in one (data-rack-cell) takes what is dropped on it -
 * an item from the rack, a device from another slot, or an asset not racked
 * yet (which opens Add for that slot). A holder doesn't go in a holder, and a
 * device in one only drags to another slot - Edit takes it out.
 */

(function () {

    var script = document.currentScript;
    var rackId = script ? script.getAttribute('data-rack-id') : '';
    var bays = document.querySelectorAll('.itflow-rack-bay[data-rack-drop]');
    if (!bays.length || !rackId) {
        return;
    }

    var drag = null;

    // Where an item of the dragged height would go with the pointer here: the
    // top row it would start on (1 is the top of the drawing), its lowest and
    // highest U, and the face - kept inside the rack
    function placeAt(bay, clientY) {
        var rows = parseInt(bay.dataset.rackUnits, 10) || 1;
        var rect = bay.getBoundingClientRect();
        var row = Math.floor((clientY - rect.top) / (rect.height / rows)) + 1;
        var top = Math.max(1, Math.min(rows - drag.height + 1, row - drag.grab));
        var start = bay.dataset.rackDescending === '1' ? top : rows - (top + drag.height - 1) + 1;
        return { top: top, start: start, end: start + drag.height - 1, face: bay.dataset.rackFace, bay: bay };
    }

    // What is in the way there, by name - '' when it is free. Something on this
    // face clashes, and for a full-depth item so does something on the other
    function inTheWay(place) {
        var faces = drag.full ? bays : [place.bay];
        for (var i = 0; i < faces.length; i++) {
            var items = faces[i].querySelectorAll('[data-rack-unit]');
            for (var j = 0; j < items.length; j++) {
                var item = items[j];
                if (drag.kind === 'unit' && item.dataset.rackUnit === drag.id) {
                    continue;
                }
                if (parseInt(item.dataset.rackStart, 10) <= place.end && parseInt(item.dataset.rackEnd, 10) >= place.start) {
                    return item.dataset.rackName || 'something';
                }
            }
        }
        return '';
    }

    function marker(bay) {
        var el = bay.querySelector('.itflow-rack-drop');
        if (!el) {
            el = document.createElement('div');
            el.className = 'itflow-rack-drop';
            el.setAttribute('aria-hidden', 'true');
            bay.appendChild(el);
        }
        return el;
    }

    function clearTargets(keep) {
        document.querySelectorAll('.itflow-rack-cell.is-target').forEach(function (el) {
            if (el !== keep) {
                el.classList.remove('is-target');
            }
        });
    }

    function clearMarkers() {
        document.querySelectorAll('.itflow-rack-drop').forEach(function (el) {
            el.remove();
        });
        clearTargets(null);
    }

    // The empty slot under the pointer that this can go in, or null
    function cellAt(bay, target) {
        var cell = target && target.closest ? target.closest('[data-rack-cell]') : null;
        return cell && bay.contains(cell) && !drag.holds ? cell : null;
    }

    function label(place) {
        return place.start === place.end ? 'U' + place.start : 'U' + place.start + '–U' + place.end;
    }

    function openAdd(place, cell) {
        var open = document.createElement('a');
        open.href = '#';
        open.className = 'ajax-modal d-none';
        open.setAttribute('data-modal-url', 'modals/rack/rack_device_add.php?id=' + encodeURIComponent(rackId)
            + '&asset_id=' + encodeURIComponent(drag.id) + (cell
                ? '&holder=' + encodeURIComponent(cell.dataset.rackHolder) + '&slot=' + encodeURIComponent(cell.dataset.rackSlot)
                : '&u=' + place.start + '&face=' + place.face));
        document.body.appendChild(open);
        open.click();
        open.remove();
    }

    function move(place) {
        var form = document.getElementById('rackMoveForm');
        if (!form) {
            return;
        }
        form.elements.unit_id.value = drag.id;
        form.elements.unit_start.value = place ? place.start : '';
        form.elements.face.value = place ? place.face : '';
        form.elements.holder.value = '';
        form.elements.slot.value = '';
        form.submit();
    }

    function moveToSlot(cell) {
        var form = document.getElementById('rackMoveForm');
        if (!form) {
            return;
        }
        form.elements.unit_id.value = drag.id;
        form.elements.unit_start.value = '';
        form.elements.face.value = '';
        form.elements.holder.value = cell.dataset.rackHolder;
        form.elements.slot.value = cell.dataset.rackSlot;
        form.submit();
    }

    document.addEventListener('dragstart', function (e) {
        var source = e.target.closest ? e.target.closest('[data-rack-drag]') : null;
        if (!source) {
            return;
        }
        var height = parseInt(source.dataset.rackHeight, 10) || 1;
        var grab = 0;
        // An item is held where it was picked up - grab its bottom U and that is what lands where you let go
        if (source.dataset.rackDrag === 'unit') {
            var rect = source.getBoundingClientRect();
            grab = Math.max(0, Math.min(height - 1, Math.floor((e.clientY - rect.top) / (rect.height / height))));
        }
        drag = {
            kind: source.dataset.rackDrag,
            id: source.dataset.rackId,
            height: height,
            grab: grab,
            full: source.dataset.rackFull === '1',
            child: source.dataset.rackChild === '1',
            holds: source.dataset.rackHolds === '1',
            from: source.dataset.rackFrom || '',
            start: parseInt(source.dataset.rackStart, 10) || 0,
            source: source
        };
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', drag.kind + ':' + drag.id);
        source.classList.add('is-dragging');
        document.body.classList.add('itflow-rack-dragging');
    });

    document.addEventListener('dragend', function () {
        if (drag) {
            drag.source.classList.remove('is-dragging');
        }
        drag = null;
        clearMarkers();
        document.body.classList.remove('itflow-rack-dragging');
    });

    bays.forEach(function (bay) {
        bay.addEventListener('dragover', function (e) {
            if (!drag) {
                return;
            }
            e.preventDefault();
            var cell = cellAt(bay, e.target);
            clearTargets(cell);
            bays.forEach(function (other) {
                if (other !== bay || cell) {
                    var old = other.querySelector('.itflow-rack-drop');
                    if (old) {
                        old.remove();
                    }
                }
            });
            e.dataTransfer.dropEffect = 'move';
            // Over an empty slot in a holder: that is where it goes
            if (cell) {
                cell.classList.add('is-target');
                return;
            }
            var place = placeAt(bay, e.clientY);
            var blocked = drag.child ? 'slot' : inTheWay(place);
            var el = marker(bay);
            el.style.gridRow = place.top + ' / span ' + drag.height;
            el.classList.toggle('is-blocked', blocked !== '');
            el.textContent = drag.child ? 'Drop it on an empty slot' : (blocked ? blocked + ' is here' : label(place));
        });

        bay.addEventListener('dragleave', function (e) {
            if (!bay.contains(e.relatedTarget)) {
                var el = bay.querySelector('.itflow-rack-drop');
                if (el) {
                    el.remove();
                }
                bay.querySelectorAll('.itflow-rack-cell.is-target').forEach(function (target) {
                    target.classList.remove('is-target');
                });
            }
        });

        bay.addEventListener('drop', function (e) {
            if (!drag) {
                return;
            }
            e.preventDefault();
            var cell = cellAt(bay, e.target);
            clearMarkers();
            if (cell) {
                if (drag.kind === 'asset') {
                    openAdd(null, cell);
                } else if (cell.dataset.rackHolder !== drag.source.dataset.rackHolder || cell.dataset.rackSlot !== drag.source.dataset.rackSlot) {
                    moveToSlot(cell);
                }
                return;
            }
            if (drag.child) {
                if (typeof itflowToast === 'function') {
                    itflowToast('Drop it on an empty slot - or take it out of the holder with Edit', 'error');
                }
                return;
            }
            var place = placeAt(bay, e.clientY);
            var blocked = inTheWay(place);
            if (blocked) {
                if (typeof itflowToast === 'function') {
                    itflowToast(label(place) + ' on the ' + place.face + ' is taken by ' + blocked, 'error');
                }
                return;
            }
            if (drag.kind === 'asset') {
                openAdd(place);
            } else if (place.start !== drag.start || place.face !== drag.from) {
                move(place);
            }
        });
    });

})();
