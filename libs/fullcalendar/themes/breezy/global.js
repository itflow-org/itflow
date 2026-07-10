/*!
FullCalendar (Vanilla JS) v7.0.0
Docs & License: https://fullcalendar.io
(c) 2026 Adam Shaw
*/
(function ({ H: joinClassNames, u, S, G: globalPlugins }) {
    

    // usually 11px font / 12px line-height
    const xxsTextClass = "fc-breezy-vQz";
    // outline
    const outlineWidthClass = "fc-breezy-0Bj";
    const outlineWidthFocusClass = "fc-breezy-uqo";
    const outlineWidthGroupFocusClass = "fc-breezy-nPR";
    const outlineOffsetClass = "fc-breezy-3Xj";
    const primaryOutlineColorClass = "fc-breezy-yg7";
    const primaryOutlineFocusClass = `${outlineWidthFocusClass} ${primaryOutlineColorClass}`;
    // neutral buttons
    const strongSolidPressableClass = joinClassNames("fc-breezy-hJa", "fc-breezy-AT7", "fc-breezy-b3B");
    const mutedHoverClass = "fc-breezy-qiG";
    const mutedHoverPressableClass = `${mutedHoverClass} fc-breezy-WfX`;
    const faintHoverClass = "fc-breezy-1EL";
    const faintHoverPressableClass = `${faintHoverClass} fc-breezy-qWF fc-breezy-UTk`;
    // controls
    const selectedClass = `fc-breezy-GfU fc-breezy-Oiq ${primaryOutlineFocusClass}`;
    const unselectedClass = `fc-breezy-t4l fc-breezy-hnE ${primaryOutlineFocusClass}`;
    // primary
    const primaryClass = "fc-breezy-Anp fc-breezy-ECg";
    const primaryPressableClass = `${primaryClass} fc-breezy-8mf`;
    const primaryPressableGroupClass = `${primaryClass} fc-breezy-t8l`;
    const primaryButtonClass = `${primaryPressableClass} fc-breezy-d0j ${primaryOutlineFocusClass} ${outlineOffsetClass}`;
    // secondary
    const secondaryClass = "fc-breezy-nyY fc-breezy-NK3";
    const secondaryPressableClass = `${secondaryClass} fc-breezy-StK`;
    const secondaryButtonClass = `${secondaryPressableClass} fc-breezy-nuP ${primaryOutlineFocusClass} fc-breezy-07j`;
    const secondaryButtonIconClass = "fc-breezy-XUJ fc-breezy-J7Y fc-breezy-b7A fc-breezy-tRE";
    // event content
    const eventMutedFgClass = "fc-breezy-WLU";
    const eventFaintBgClass = "fc-breezy-GjO";
    const eventFaintPressableClass = joinClassNames(eventFaintBgClass, "fc-breezy-yE8", "fc-breezy-x2T");
    // interactive neutral foregrounds
    const mutedFgPressableGroupClass = "fc-breezy-t4l fc-breezy-Wxh fc-breezy-x7E";
    // transparent resizer for mouse
    const blockPointerResizerClass = "fc-breezy-1EY fc-breezy-pps fc-breezy-vs6";
    const rowPointerResizerClass = `${blockPointerResizerClass} fc-breezy-AWB fc-breezy-hza`;
    const columnPointerResizerClass = `${blockPointerResizerClass} fc-breezy-MaV fc-breezy-uuA`;
    // circle resizer for touch
    const blockTouchResizerClass = "fc-breezy-1EY fc-breezy-3wQ fc-breezy-wsy fc-breezy-lNM fc-breezy-gmc fc-breezy-AAA";
    const rowTouchResizerClass = `${blockTouchResizerClass} fc-breezy-ERR fc-breezy-Dq8`;
    const columnTouchResizerClass = `${blockTouchResizerClass} fc-breezy-1V6 fc-breezy-F99`;
    const getNormalDayHeaderBorderClass = (info) => joinClassNames(!info.inPopover && (info.isMajor ? "fc-breezy-wsy fc-breezy-OFc" :
        !info.isNarrow && "fc-breezy-wsy fc-breezy-EAo"));
    const getMutedDayHeaderBorderClass = (info) => joinClassNames(!info.inPopover && (info.isMajor ? "fc-breezy-wsy fc-breezy-OFc" :
        !info.isNarrow && "fc-breezy-wsy fc-breezy-tTN"));
    const getNormalDayCellBorderColorClass = (info) => (info.isMajor ? "fc-breezy-OFc" : "fc-breezy-EAo");
    const getMutedDayCellBorderColorClass = (info) => (info.isMajor ? "fc-breezy-OFc" : "fc-breezy-tTN");
    const tallDayCellBottomClass = "fc-breezy-mhE";
    const getShortDayCellBottomClass = (info) => joinClassNames(!info.isNarrow && "fc-breezy-toR");
    const mutedHoverButtonClass = joinClassNames(mutedHoverPressableClass, outlineWidthFocusClass, primaryOutlineColorClass);
    const dayRowCommonClasses = {
        /* Day Row > List-Item Event
        ----------------------------------------------------------------------------------------------- */
        listItemEventClass: (info) => joinClassNames("fc-breezy-Ika fc-breezy-7A6", info.isNarrow
            ? "fc-breezy-148 fc-breezy-Fvv"
            : "fc-breezy-rVY fc-breezy-KzJ", info.isSelected
            ? "fc-breezy-pZQ"
            : info.isInteractive
                ? mutedHoverPressableClass
                : mutedHoverClass),
        listItemEventInnerClass: (info) => joinClassNames("fc-breezy-dl1 fc-breezy-1sP fc-breezy-XpK fc-breezy-N2M", info.isNarrow
            ? `fc-breezy-z5u ${xxsTextClass}`
            : "fc-breezy-2rx fc-breezy-a3B"),
        listItemEventTimeClass: (info) => joinClassNames(info.isNarrow ? "fc-breezy-F1o" : "fc-breezy-oQ2", "fc-breezy-t4l fc-breezy-NPw fc-breezy-TZ4 fc-breezy-pKG fc-breezy-1Zl"),
        listItemEventTitleClass: (info) => joinClassNames(info.isNarrow ? "fc-breezy-F1o" : "fc-breezy-oQ2", "fc-breezy-Oiq fc-breezy-1OT fc-breezy-TZ4 fc-breezy-pKG fc-breezy-OLq", info.timeText && "fc-breezy-IPx"),
        /* Day Row > Row Event
        ----------------------------------------------------------------------------------------------- */
        rowEventClass: (info) => joinClassNames(info.isStart && (info.isNarrow ? "fc-breezy-Jzj" : "fc-breezy-Wga"), info.isEnd && (info.isNarrow ? "fc-breezy-3e1" : "fc-breezy-KYn")),
        rowEventInnerClass: (info) => info.isNarrow ? "fc-breezy-z5u" : "fc-breezy-2rx",
        /* Day Row > More-Link
        ----------------------------------------------------------------------------------------------- */
        rowMoreLinkClass: (info) => joinClassNames("fc-breezy-Ika fc-breezy-wsy", info.isNarrow
            ? "fc-breezy-148 fc-breezy-UIT fc-breezy-Fvv"
            : "fc-breezy-sI7 fc-breezy-rVY fc-breezy-d0j fc-breezy-KzJ", mutedHoverPressableClass),
        rowMoreLinkInnerClass: (info) => joinClassNames(info.isNarrow
            ? `fc-breezy-7A6 ${xxsTextClass}`
            : "fc-breezy-KUX fc-breezy-a3B", "fc-breezy-Oiq"),
    };
    var index = {
        name: "theme-breezy",
        optionDefaults: {
            className: (info) => joinClassNames("fc-breezy-gmc fc-breezy-n5m", !(info.borderlessTop || info.borderlessBottom || info.borderlessX) && "fc-breezy-hny"),
            viewClass: (info) => {
                const hasBorderTop = !info.options.headerToolbar && !info.borderlessTop;
                const hasBorderBottom = !info.options.footerToolbar && !info.borderlessBottom;
                const hasBorderX = !info.borderlessX;
                return joinClassNames("fc-breezy-EAo", hasBorderTop && "fc-breezy-ku3", hasBorderBottom && "fc-breezy-zi1", hasBorderX && "fc-breezy-1Wx", (hasBorderTop && hasBorderX) && "fc-breezy-wko", (hasBorderBottom && hasBorderX) && "fc-breezy-L2o", !info.isHeightAuto && "fc-breezy-pKG");
            },
            /* Toolbar
            --------------------------------------------------------------------------------------------- */
            toolbarClass: (info) => joinClassNames("fc-breezy-KRz fc-breezy-OEz fc-breezy-nYK fc-breezy-dl1 fc-breezy-1sP fc-breezy-dNl fc-breezy-XpK fc-breezy-N2M fc-breezy-Pms fc-breezy-pKG fc-breezy-EAo", !info.borderlessX && "fc-breezy-1Wx"),
            headerToolbarClass: (info) => joinClassNames("fc-breezy-zi1", !info.borderlessTop && "fc-breezy-ku3", !(info.borderlessTop || info.borderlessX) && "fc-breezy-wko"),
            footerToolbarClass: (info) => joinClassNames("fc-breezy-ku3", !info.borderlessBottom && "fc-breezy-zi1", !(info.borderlessBottom || info.borderlessX) && "fc-breezy-L2o"),
            toolbarSectionClass: "fc-breezy-yi0 fc-breezy-dl1 fc-breezy-1sP fc-breezy-XpK fc-breezy-Pms",
            toolbarTitleClass: "fc-breezy-9ZS fc-breezy-C8a fc-breezy-Oiq",
            buttonGroupClass: (info) => joinClassNames("fc-breezy-dl1 fc-breezy-1sP fc-breezy-XpK", !info.hasSelection && "fc-breezy-KzJ fc-breezy-eSM"),
            buttonClass: (info) => joinClassNames("fc-breezy-bCs fc-breezy-dl6 fc-breezy-dl1 fc-breezy-1sP fc-breezy-XpK fc-breezy-9yp fc-breezy-Z9U", info.isIconOnly ? "fc-breezy-Nca" : "fc-breezy-Apf", info.buttonGroup?.hasSelection ? joinClassNames("fc-breezy-KzJ fc-breezy-1OT", info.isSelected
                ? selectedClass
                : unselectedClass) : joinClassNames("fc-breezy-C8a", info.isPrimary
                ? primaryButtonClass
                : secondaryButtonClass, info.buttonGroup
                ? "fc-breezy-Ps8 fc-breezy-H1W fc-breezy-g3A fc-breezy-7ss fc-breezy-JIC"
                : "fc-breezy-KzJ fc-breezy-eSM fc-breezy-wsy")),
            buttons: {
                prev: {
                    iconContent: () => chevronDown(joinClassNames(secondaryButtonIconClass, "fc-breezy-z44 fc-breezy-keW")),
                },
                next: {
                    iconContent: () => chevronDown(joinClassNames(secondaryButtonIconClass, "fc-breezy-KxI fc-breezy-ZW3")),
                },
                prevYear: {
                    iconContent: () => chevronDoubleLeft(joinClassNames(secondaryButtonIconClass, "fc-breezy-asP"))
                },
                nextYear: {
                    iconContent: () => chevronDoubleLeft(joinClassNames(secondaryButtonIconClass, "fc-breezy-jmT fc-breezy-jY6"))
                },
            },
            /* Abstract Event
            --------------------------------------------------------------------------------------------- */
            eventShortHeight: 50,
            eventColor: "var(--fc-breezy-event)",
            eventContrastColor: "var(--fc-breezy-event-contrast)",
            eventClass: (info) => joinClassNames(info.isDragging && "fc-breezy-n5m", info.event.url && "fc-breezy-JiE", info.isSelected
                ? joinClassNames(outlineWidthClass, info.isDragging && "fc-breezy-tkw")
                : outlineWidthFocusClass, primaryOutlineColorClass),
            /* Background Event
            --------------------------------------------------------------------------------------------- */
            backgroundEventColor: "var(--fc-breezy-background-event)",
            backgroundEventClass: "fc-breezy-aPk fc-breezy-jsy fc-breezy-DO7",
            backgroundEventTitleClass: (info) => joinClassNames("fc-breezy-lMo fc-breezy-L1Y", info.isNarrow
                ? `fc-breezy-iS4 ${xxsTextClass}`
                : "fc-breezy-3N5 fc-breezy-a3B", "fc-breezy-sI1"),
            /* Block Event
            --------------------------------------------------------------------------------------------- */
            blockEventClass: (info) => joinClassNames("fc-breezy-bCs fc-breezy-eYX fc-breezy-vwH fc-breezy-d0j fc-breezy-DO7", info.isInteractive ? eventFaintPressableClass : eventFaintBgClass, (info.isDragging && !info.isSelected) && "fc-breezy-iTG"),
            blockEventInnerClass: eventMutedFgClass,
            blockEventTimeClass: "fc-breezy-TZ4 fc-breezy-pKG fc-breezy-1Zl",
            blockEventTitleClass: "fc-breezy-TZ4 fc-breezy-pKG fc-breezy-OLq",
            /* Row Event
            --------------------------------------------------------------------------------------------- */
            rowEventClass: (info) => joinClassNames("fc-breezy-Ika fc-breezy-JIC", info.isStart && joinClassNames("fc-breezy-3J4", info.isNarrow ? "fc-breezy-kmj" : "fc-breezy-QUg"), info.isEnd && joinClassNames("fc-breezy-USt", info.isNarrow ? "fc-breezy-Skl" : "fc-breezy-RNO")),
            rowEventBeforeClass: (info) => joinClassNames(info.isStartResizable && joinClassNames(info.isSelected ? rowTouchResizerClass : rowPointerResizerClass, "fc-breezy-11a")),
            rowEventAfterClass: (info) => joinClassNames(info.isEndResizable && joinClassNames(info.isSelected ? rowTouchResizerClass : rowPointerResizerClass, "fc-breezy-Tuc")),
            rowEventInnerClass: (info) => joinClassNames("fc-breezy-dl1 fc-breezy-1sP fc-breezy-XpK", info.isNarrow ? xxsTextClass : "fc-breezy-a3B"),
            rowEventTimeClass: (info) => joinClassNames(info.isNarrow ? "fc-breezy-a7i" : "fc-breezy-C2j", "fc-breezy-1OT"),
            rowEventTitleClass: (info) => (info.isNarrow ? "fc-breezy-oQ2" : "fc-breezy-aCI"),
            /* Column Event
            --------------------------------------------------------------------------------------------- */
            columnEventClass: (info) => joinClassNames("fc-breezy-1Wx fc-breezy-A3h fc-breezy-9Iz", info.isStart && joinClassNames("fc-breezy-ku3 fc-breezy-wko", info.isNarrow ? "fc-breezy-sEX" : "fc-breezy-zZM"), info.isEnd && joinClassNames("fc-breezy-zi1 fc-breezy-L2o", info.isNarrow ? "fc-breezy-Ika" : "fc-breezy-vUo")),
            columnEventBeforeClass: (info) => joinClassNames(info.isStartResizable && joinClassNames(info.isSelected ? columnTouchResizerClass : columnPointerResizerClass, "fc-breezy-YDC")),
            columnEventAfterClass: (info) => joinClassNames(info.isEndResizable && joinClassNames(info.isSelected ? columnTouchResizerClass : columnPointerResizerClass, "fc-breezy-fJL")),
            columnEventInnerClass: (info) => joinClassNames("fc-breezy-dl1", info.isShort
                ? "fc-breezy-1sP fc-breezy-XpK fc-breezy-NWN fc-breezy-iS4"
                : joinClassNames("fc-breezy-sgX", info.isNarrow ? "fc-breezy-aCI fc-breezy-2rx" : "fc-breezy-Nca fc-breezy-Jhn"), (info.isShort || info.isNarrow) ? xxsTextClass : "fc-breezy-a3B"),
            columnEventTimeClass: (info) => (!info.isShort && (info.isNarrow ? "fc-breezy-166" : "fc-breezy-4dx")),
            columnEventTitleClass: (info) => joinClassNames(!info.isShort && (info.isNarrow ? "fc-breezy-2rx" : "fc-breezy-Jhn"), "fc-breezy-C8a"),
            /* More-Link
            --------------------------------------------------------------------------------------------- */
            moreLinkClass: `${outlineWidthFocusClass} ${primaryOutlineColorClass}`,
            moreLinkInnerClass: "fc-breezy-TZ4 fc-breezy-pKG",
            columnMoreLinkClass: (info) => joinClassNames(info.isNarrow ? "fc-breezy-SEP" : "fc-breezy-V9v", `fc-breezy-wsy fc-breezy-d0j fc-breezy-4MR fc-breezy-KzJ ${strongSolidPressableClass} fc-breezy-vwH fc-breezy-A3h fc-breezy-9Iz`),
            columnMoreLinkInnerClass: (info) => joinClassNames(info.isNarrow
                ? `fc-breezy-KUX ${xxsTextClass}`
                : "fc-breezy-iS4 fc-breezy-a3B", "fc-breezy-sI1"),
            /* Day Header
            --------------------------------------------------------------------------------------------- */
            dayHeaderAlign: (info) => info.inPopover ? "start" : "center",
            dayHeaderClass: (info) => joinClassNames("fc-breezy-E9P", info.inPopover && "fc-breezy-zi1 fc-breezy-EAo fc-breezy-nYK"),
            dayHeaderInnerClass: (info) => joinClassNames("fc-breezy-dl1 fc-breezy-1sP fc-breezy-XpK", (!info.dayNumberText && !info.inPopover)
                ? joinClassNames("fc-breezy-Jhn fc-breezy-Fvv fc-breezy-a3B", info.isNarrow
                    ? "fc-breezy-aCI fc-breezy-gMS fc-breezy-t4l"
                    : "fc-breezy-ZrE fc-breezy-bvX fc-breezy-C8a fc-breezy-sI1", info.hasNavLink && mutedHoverButtonClass)
                : (info.isToday && info.dayNumberText && !info.inPopover)
                    ? joinClassNames("fc-breezy-bCs fc-breezy-bvX fc-breezy-hS8", info.isNarrow ? "fc-breezy-TFV" : "fc-breezy-I1A")
                    : joinClassNames("fc-breezy-Fvv", info.inPopover
                        ? "fc-breezy-bvX fc-breezy-aCI fc-breezy-2rx"
                        : joinClassNames("fc-breezy-fn8 fc-breezy-TFV fc-breezy-ZrE", info.isNarrow ? "fc-breezy-2tF" : "fc-breezy-X6C"), info.hasNavLink && mutedHoverButtonClass)),
            dayHeaderContent: (info) => ((!info.dayNumberText && !info.inPopover) ? (u(S, { children: info.text })) : (u(S, { children: info.textParts.map((textPart, i) => (u("span", { className: joinClassNames("fc-breezy-jm6", info.isNarrow ? "fc-breezy-a3B" : "fc-breezy-9yp", textPart.type === "day"
                        ? joinClassNames("fc-breezy-dl1 fc-breezy-1sP fc-breezy-XpK", !info.isNarrow && "fc-breezy-C8a", (info.isToday && !info.inPopover)
                            ? joinClassNames("fc-breezy-cKZ fc-breezy-AAA fc-breezy-E9P", info.isNarrow ? "fc-breezy-MSG" : "fc-breezy-n6w", info.hasNavLink
                                ? `${primaryPressableGroupClass} ${outlineWidthGroupFocusClass} ${outlineOffsetClass} ${primaryOutlineColorClass}`
                                : primaryClass)
                            : "fc-breezy-Oiq")
                        : "fc-breezy-t4l"), children: textPart.value }, i))) }))),
            /* Day Cell
            --------------------------------------------------------------------------------------------- */
            dayCellClass: (info) => joinClassNames("fc-breezy-wsy", ((info.isOther || info.isDisabled) && !info.options.businessHours) && "fc-breezy-nYK"),
            dayCellTopClass: (info) => joinClassNames(info.isNarrow ? "fc-breezy-84e" : "fc-breezy-p7s", "fc-breezy-dl1 fc-breezy-1sP"),
            dayCellTopInnerClass: (info) => joinClassNames("fc-breezy-dl1 fc-breezy-1sP fc-breezy-XpK fc-breezy-E9P fc-breezy-TZ4", info.isNarrow
                ? `fc-breezy-SEP fc-breezy-oM6 ${xxsTextClass}`
                : "fc-breezy-V9v fc-breezy-TFV fc-breezy-a3B", info.isToday
                ? joinClassNames("fc-breezy-AAA fc-breezy-C8a", info.isNarrow ? "fc-breezy-qvL" : "fc-breezy-Wga", info.text === info.dayNumberText
                    ? (info.isNarrow ? "fc-breezy-79F" : "fc-breezy-ilz")
                    : (info.isNarrow ? "fc-breezy-aCI" : "fc-breezy-Nca"), info.hasNavLink
                    ? `${primaryPressableClass} ${outlineOffsetClass}`
                    : primaryClass)
                : joinClassNames("fc-breezy-Skl", info.isNarrow ? "fc-breezy-aCI" : "fc-breezy-Nca", info.hasNavLink && mutedHoverPressableClass, info.isOther
                    ? "fc-breezy-mNS"
                    : (info.monthText ? "fc-breezy-sI1" : "fc-breezy-t4l"), info.monthText && "fc-breezy-DIS")),
            dayCellInnerClass: (info) => joinClassNames(info.inPopover && "fc-breezy-3N5"),
            /* Popover
            --------------------------------------------------------------------------------------------- */
            popoverClass: "fc-breezy-uhF fc-breezy-wsy fc-breezy-LDJ fc-breezy-hny fc-breezy-pKG fc-breezy-1kP fc-breezy-gMS fc-breezy-aNc fc-breezy-n5m",
            popoverCloseClass: `fc-breezy-bCs fc-breezy-1EY fc-breezy-SKv fc-breezy-aYN fc-breezy-KUX fc-breezy-Fvv ${mutedHoverButtonClass} fc-breezy-Z9U`,
            popoverCloseContent: () => x(`fc-breezy-XUJ ${mutedFgPressableGroupClass}`),
            /* Lane
            --------------------------------------------------------------------------------------------- */
            dayLaneClass: (info) => joinClassNames("fc-breezy-wsy", info.isMajor ? "fc-breezy-OFc" : "fc-breezy-tTN", info.isDisabled && "fc-breezy-nYK"),
            dayLaneInnerClass: (info) => (info.isStack
                ? "fc-breezy-gMS"
                : info.isNarrow ? "fc-breezy-148" : "fc-breezy-rVY"),
            slotLaneClass: (info) => joinClassNames("fc-breezy-wsy fc-breezy-tTN", info.isMinor && "fc-breezy-TN2"),
            /* List Day
            --------------------------------------------------------------------------------------------- */
            listDaysClass: "fc-breezy-8T7 fc-breezy-g0K fc-breezy-6u7 fc-breezy-E7b fc-breezy-KRz",
            listDayClass: (info) => joinClassNames(!info.isLast && "fc-breezy-zi1 fc-breezy-tTN", "fc-breezy-dl1 fc-breezy-1sP fc-breezy-EF4 fc-breezy-tgZ"),
            listDayHeaderClass: "fc-breezy-SEP fc-breezy-yi0 fc-breezy-vVE fc-breezy-kMV fc-breezy-MKw fc-breezy-dl1 fc-breezy-sgX fc-breezy-EF4",
            listDayHeaderInnerClass: (info) => joinClassNames("fc-breezy-cJ3 fc-breezy-2rx fc-breezy-Nca fc-breezy-PtF fc-breezy-AAA fc-breezy-9yp", !info.level
                ? joinClassNames(info.isToday
                    ? joinClassNames("fc-breezy-C8a", info.hasNavLink ? primaryPressableClass : primaryClass)
                    : joinClassNames("fc-breezy-1OT fc-breezy-Oiq", info.hasNavLink && mutedHoverPressableClass))
                : joinClassNames("fc-breezy-mNS", info.hasNavLink && `${mutedHoverPressableClass} fc-breezy-i3P`)),
            listDayBodyClass: "fc-breezy-9Qs fc-breezy-1El fc-breezy-2KU fc-breezy-wsy fc-breezy-EAo fc-breezy-KzJ",
            /* Single Month (in Multi-Month)
            --------------------------------------------------------------------------------------------- */
            singleMonthClass: (info) => joinClassNames(info.multiMonthColumns > 1 && "fc-breezy-jD5", (info.multiMonthColumns === 1 && !info.isLast) && "fc-breezy-zi1 fc-breezy-EAo"),
            singleMonthHeaderClass: (info) => joinClassNames(info.multiMonthColumns > 1
                ? "fc-breezy-x96"
                : "fc-breezy-End fc-breezy-gmc fc-breezy-zi1 fc-breezy-EAo", "fc-breezy-XpK"),
            singleMonthHeaderInnerClass: (info) => joinClassNames("fc-breezy-Jhn fc-breezy-Nca fc-breezy-KzJ fc-breezy-9yp fc-breezy-Oiq fc-breezy-C8a", info.hasNavLink && mutedHoverPressableClass),
            /* Misc Table
            --------------------------------------------------------------------------------------------- */
            tableHeaderClass: "fc-breezy-gmc",
            fillerClass: "fc-breezy-wsy fc-breezy-tTN",
            dayNarrowWidth: 100,
            dayHeaderRowClass: "fc-breezy-wsy fc-breezy-tTN",
            dayRowClass: "fc-breezy-wsy fc-breezy-EAo",
            slotHeaderRowClass: "fc-breezy-wsy fc-breezy-EAo",
            slotHeaderInnerClass: "fc-breezy-mNS fc-breezy-XHd",
            /* Misc Content
            --------------------------------------------------------------------------------------------- */
            navLinkClass: `${outlineWidthFocusClass} ${primaryOutlineColorClass}`,
            inlineWeekNumberClass: (info) => joinClassNames("fc-breezy-1EY fc-breezy-n9G fc-breezy-iD1 fc-breezy-gmc fc-breezy-t4l fc-breezy-TZ4 fc-breezy-4In fc-breezy-zi1 fc-breezy-k8g fc-breezy-3J4 fc-breezy-Hhp", info.isNarrow
                ? `fc-breezy-KUX ${xxsTextClass}`
                : "fc-breezy-XJa fc-breezy-a3B", info.hasNavLink
                ? `${mutedHoverPressableClass} fc-breezy-07j`
                : mutedHoverClass),
            highlightClass: "fc-breezy-xAy",
            nonBusinessHoursClass: "fc-breezy-nYK",
            nowIndicatorLineClass: "fc-breezy-CH7 fc-breezy-qQW fc-breezy-D9l",
            nowIndicatorDotClass: "fc-breezy-aAW fc-breezy-Vpk fc-breezy-D9l fc-breezy-63n fc-breezy-AAA fc-breezy-GBJ fc-breezy-9Iz",
            /* Resource Day Header
            --------------------------------------------------------------------------------------------- */
            resourceDayHeaderAlign: "center",
            resourceDayHeaderClass: "fc-breezy-wsy",
            resourceDayHeaderInnerClass: (info) => joinClassNames("fc-breezy-bvX fc-breezy-sI1 fc-breezy-C8a", info.isNarrow ? "fc-breezy-a3B" : "fc-breezy-9yp"),
            /* Resource Data Grid
            --------------------------------------------------------------------------------------------- */
            resourceColumnHeaderClass: "fc-breezy-wsy fc-breezy-tTN fc-breezy-E9P",
            resourceColumnHeaderInnerClass: "fc-breezy-bvX fc-breezy-sI1 fc-breezy-9yp",
            resourceColumnResizerClass: "fc-breezy-1EY fc-breezy-AWB fc-breezy-4Tv fc-breezy-dnf",
            resourceGroupHeaderClass: "fc-breezy-wsy fc-breezy-EAo fc-breezy-pZQ",
            resourceGroupHeaderInnerClass: "fc-breezy-bvX fc-breezy-sI1 fc-breezy-9yp",
            resourceCellClass: "fc-breezy-wsy fc-breezy-tTN",
            resourceCellInnerClass: "fc-breezy-bvX fc-breezy-sI1 fc-breezy-9yp",
            resourceIndentClass: "fc-breezy-Wga fc-breezy-p9t fc-breezy-E9P",
            resourceExpanderClass: `fc-breezy-bCs fc-breezy-KUX fc-breezy-AAA ${mutedHoverPressableClass} ${outlineWidthFocusClass} ${primaryOutlineColorClass}`,
            resourceExpanderContent: (info) => chevronDown(joinClassNames(`fc-breezy-XUJ ${mutedFgPressableGroupClass}`, !info.isExpanded && "fc-breezy-KxI fc-breezy-ZW3")),
            resourceHeaderRowClass: "fc-breezy-wsy fc-breezy-EAo",
            resourceRowClass: "fc-breezy-wsy fc-breezy-EAo",
            resourceColumnDividerClass: "fc-breezy-USt fc-breezy-OFc",
            /* Timeline Lane
            --------------------------------------------------------------------------------------------- */
            resourceGroupLaneClass: "fc-breezy-wsy fc-breezy-EAo fc-breezy-pZQ",
            resourceLaneClass: "fc-breezy-wsy fc-breezy-EAo",
            resourceLaneBottomClass: (info) => joinClassNames(info.options.eventOverlap && "fc-breezy-uuA"),
            timelineBottomClass: "fc-breezy-uuA",
        },
        views: {
            dayGrid: {
                ...dayRowCommonClasses,
                dayHeaderClass: getNormalDayHeaderBorderClass,
                dayHeaderDividerClass: "fc-breezy-zi1 fc-breezy-OFc",
                dayCellClass: getNormalDayCellBorderColorClass,
                dayCellBottomClass: getShortDayCellBottomClass,
                backgroundEventInnerClass: "fc-breezy-dl1 fc-breezy-1sP fc-breezy-LMv",
            },
            multiMonth: {
                ...dayRowCommonClasses,
                dayHeaderClass: getNormalDayHeaderBorderClass,
                dayHeaderDividerClass: (info) => joinClassNames(info.multiMonthColumns === 1 && "fc-breezy-zi1 fc-breezy-OFc fc-breezy-qNs"),
                dayCellClass: getNormalDayCellBorderColorClass,
                dayCellBottomClass: getShortDayCellBottomClass,
                tableBodyClass: (info) => joinClassNames(info.multiMonthColumns > 1 && "fc-breezy-wsy fc-breezy-EAo fc-breezy-KzJ fc-breezy-eSM fc-breezy-pKG"),
            },
            timeGrid: {
                ...dayRowCommonClasses,
                dayHeaderClass: getMutedDayHeaderBorderClass,
                dayHeaderDividerClass: (info) => joinClassNames("fc-breezy-zi1", info.options.allDaySlot
                    ? "fc-breezy-EAo"
                    : "fc-breezy-OFc fc-breezy-rf6"),
                dayCellClass: getMutedDayCellBorderColorClass,
                dayCellBottomClass: tallDayCellBottomClass,
                /* TimeGrid > Week Number Header
                ------------------------------------------------------------------------------------------- */
                weekNumberHeaderClass: "fc-breezy-XpK fc-breezy-LMv",
                weekNumberHeaderInnerClass: (info) => joinClassNames("fc-breezy-cGD fc-breezy-TFV fc-breezy-ZrE fc-breezy-t4l fc-breezy-Fvv fc-breezy-dl1 fc-breezy-1sP fc-breezy-XpK", info.hasNavLink && mutedHoverPressableClass, info.isNarrow ? "fc-breezy-a3B" : "fc-breezy-9yp"),
                /* TimeGrid > All-Day Header
                ------------------------------------------------------------------------------------------- */
                allDayHeaderClass: "fc-breezy-XpK",
                allDayHeaderInnerClass: (info) => joinClassNames("fc-breezy-rUb fc-breezy-mNS", info.isNarrow ? xxsTextClass : "fc-breezy-a3B"),
                allDayDividerClass: "fc-breezy-zi1 fc-breezy-OFc fc-breezy-rf6",
                /* TimeGrid > Slot Header
                ------------------------------------------------------------------------------------------- */
                slotHeaderClass: "fc-breezy-LMv",
                slotHeaderInnerClass: (info) => joinClassNames("fc-breezy-eYX fc-breezy-GFf fc-breezy-2tF", info.isNarrow
                    ? `fc-breezy-Cy2 ${xxsTextClass}`
                    : "fc-breezy-uqG fc-breezy-a3B", info.isFirst && "fc-breezy-pps"),
                slotHeaderDividerClass: "fc-breezy-USt fc-breezy-tTN",
            },
            list: {
                /* List-View > List-Item Event
                ------------------------------------------------------------------------------------------- */
                listItemEventClass: (info) => joinClassNames("fc-breezy-bCs fc-breezy-lqx fc-breezy-XpK fc-breezy-wwb", !info.isLast && "fc-breezy-zi1 fc-breezy-tTN", info.isInteractive
                    ? faintHoverPressableClass
                    : faintHoverClass),
                listItemEventBeforeClass: "fc-breezy-5JF fc-breezy-lNM fc-breezy-AAA",
                listItemEventInnerClass: "fc-breezy-dl1 fc-breezy-1sP fc-breezy-XpK fc-breezy-wwb fc-breezy-9yp",
                listItemEventTimeClass: "fc-breezy-yi0 fc-breezy-roZ fc-breezy-kMV fc-breezy-TZ4 fc-breezy-pKG fc-breezy-IPx fc-breezy-t4l",
                listItemEventTitleClass: (info) => joinClassNames("fc-breezy-1El fc-breezy-2KU fc-breezy-1OT fc-breezy-TZ4 fc-breezy-pKG fc-breezy-sI1", info.event.url && "fc-breezy-Ogp"),
                /* No-Events Screen
                ------------------------------------------------------------------------------------------- */
                noEventsClass: "fc-breezy-1El fc-breezy-dl1 fc-breezy-sgX fc-breezy-XpK fc-breezy-E9P",
                noEventsInnerClass: "fc-breezy-P9h fc-breezy-t4l",
            },
            resourceDayGrid: {
                resourceDayHeaderClass: (info) => (info.isMajor
                    ? "fc-breezy-OFc"
                    : "fc-breezy-EAo"),
            },
            resourceTimeGrid: {
                resourceDayHeaderClass: (info) => (info.isMajor
                    ? "fc-breezy-OFc"
                    : "fc-breezy-tTN"),
            },
            timeline: {
                /* Timeline > Row Event
                ------------------------------------------------------------------------------------------- */
                rowEventClass: (info) => info.isEnd && "fc-breezy-9hC",
                rowEventInnerClass: (info) => info.options.eventOverlap ? "fc-breezy-Jhn" : "fc-breezy-dl6",
                /* Timeline > More-Link
                ------------------------------------------------------------------------------------------- */
                rowMoreLinkClass: `fc-breezy-9hC fc-breezy-Ika fc-breezy-wsy fc-breezy-d0j fc-breezy-4MR fc-breezy-KzJ ${strongSolidPressableClass} fc-breezy-vwH`,
                rowMoreLinkInnerClass: "fc-breezy-iS4 fc-breezy-sI1 fc-breezy-a3B",
                /* Timeline > Slot Header
                ------------------------------------------------------------------------------------------- */
                slotHeaderAlign: (info) => info.isTime ? "start" : "center",
                slotHeaderClass: (info) => joinClassNames(info.level > 0 && "fc-breezy-wsy fc-breezy-tTN", "fc-breezy-LMv"),
                slotHeaderInnerClass: (info) => joinClassNames("fc-breezy-GFf fc-breezy-2tF fc-breezy-a3B", info.isTime && joinClassNames("fc-breezy-eYX fc-breezy-OAt", info.isFirst && "fc-breezy-pps"), info.hasNavLink && "fc-breezy-Eu0"),
                slotHeaderDividerClass: "fc-breezy-zi1 fc-breezy-OFc fc-breezy-qNs",
            },
        },
    };
    /* SVGs
    ------------------------------------------------------------------------------------------------- */
    function chevronDown(className) {
        return u("svg", { className: className, xmlns: "http://www.w3.org/2000/svg", viewBox: "0 0 20 20", fill: "currentColor", children: u("path", { fillRule: "evenodd", d: "M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z", clipRule: "evenodd" }) });
    }
    function chevronDoubleLeft(className) {
        return u("svg", { className: className, xmlns: "http://www.w3.org/2000/svg", viewBox: "0 0 20 20", fill: "currentColor", children: u("path", { fillRule: "evenodd", d: "M4.72 9.47a.75.75 0 0 0 0 1.06l4.25 4.25a.75.75 0 1 0 1.06-1.06L6.31 10l3.72-3.72a.75.75 0 1 0-1.06-1.06L4.72 9.47Zm9.25-4.25L9.72 9.47a.75.75 0 0 0 0 1.06l4.25 4.25a.75.75 0 1 0 1.06-1.06L11.31 10l3.72-3.72a.75.75 0 0 0-1.06-1.06Z", clipRule: "evenodd" }) });
    }
    function x(className) {
        return u("svg", { className: className, xmlns: "http://www.w3.org/2000/svg", viewBox: "0 0 20 20", fill: "currentColor", children: u("path", { d: "M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" }) });
    }

    globalPlugins.push(index);

})(FullCalendar.Shared);
