/*!
FullCalendar (Vanilla JS) v7.0.0
Docs & License: https://fullcalendar.io
(c) 2026 Adam Shaw
*/
(function ({ H: joinClassNames, u, S, G: globalPlugins }) {
    

    // usually 11px font / 12px line-height
    const xxsTextClass = "fc-monarch-vQz";
    // outline
    const outlineWidthClass = "fc-monarch-x7S";
    const outlineWidthFocusClass = "fc-monarch-sOR";
    const outlineWidthGroupFocusClass = "fc-monarch-a9l";
    const outlineColorClass = "fc-monarch-MJY";
    const outlineFocusClass = `${outlineColorClass} ${outlineWidthFocusClass}`;
    // neutral buttons
    const strongSolidPressableClass = joinClassNames("fc-monarch-9LE", "fc-monarch-r63", "fc-monarch-ljX");
    const mutedHoverClass = "fc-monarch-v08";
    const mutedHoverGroupClass = "fc-monarch-JQh";
    const mutedHoverPressableClass = `${mutedHoverClass} fc-monarch-Yib fc-monarch-6MP`;
    const mutedHoverPressableGroupClass = `${mutedHoverGroupClass} fc-monarch-4Pp fc-monarch-Hsn`;
    // controls
    const selectedClass = "fc-monarch-y37 fc-monarch-bIt";
    const selectedPressableClasss = `${selectedClass} fc-monarch-SJz fc-monarch-MQC`;
    const selectedButtonClass = `${selectedPressableClasss} fc-monarch-wsy fc-monarch-d0j ${outlineFocusClass} fc-monarch-07j`;
    const unselectedButtonClass = `${mutedHoverPressableClass} fc-monarch-wsy fc-monarch-d0j ${outlineFocusClass} fc-monarch-07j`;
    // primary
    const primaryClass = "fc-monarch-zue fc-monarch-8ys";
    const primaryPressableClass = `${primaryClass} fc-monarch-4bi fc-monarch-yEk`;
    const primaryButtonClass = `${primaryPressableClass} fc-monarch-wsy fc-monarch-d0j ${outlineFocusClass}`;
    // secondary *calendar content* (has muted color)
    const secondaryClass = "fc-monarch-XMK fc-monarch-c3e";
    const secondaryPressableClass = `${secondaryClass} fc-monarch-ISM fc-monarch-KwH ${outlineFocusClass}`;
    // secondary *toolbar button* (neutral)
    const secondaryButtonClass = `${mutedHoverPressableClass} fc-monarch-wsy fc-monarch-zOd ${outlineFocusClass} fc-monarch-07j`;
    const secondaryButtonIconClass = `fc-monarch-XUJ fc-monarch-PVh fc-monarch-zn9 fc-monarch-Bpp`;
    // tertiary
    const tertiaryClass = "fc-monarch-4os fc-monarch-CCH";
    const tertiaryPressableClass = `${tertiaryClass} fc-monarch-hbI fc-monarch-yo2`;
    const tertiaryPressableGroupClass = `${tertiaryClass} fc-monarch-Yws fc-monarch-jc4 ${outlineFocusClass}`;
    // interactive neutral foregrounds
    const mutedFgPressableGroupClass = "fc-monarch-JMv fc-monarch-rih fc-monarch-8El";
    // transparent resizer for mouse
    const blockPointerResizerClass = "fc-monarch-1EY fc-monarch-pps fc-monarch-vs6";
    const rowPointerResizerClass = `${blockPointerResizerClass} fc-monarch-AWB fc-monarch-hza`;
    const columnPointerResizerClass = `${blockPointerResizerClass} fc-monarch-MaV fc-monarch-uuA`;
    // circle resizer for touch
    const blockTouchResizerClass = "fc-monarch-1EY fc-monarch-3wQ fc-monarch-wsy fc-monarch-lNM fc-monarch-MAH fc-monarch-AAA";
    const rowTouchResizerClass = `${blockTouchResizerClass} fc-monarch-ERR fc-monarch-Dq8`;
    const columnTouchResizerClass = `${blockTouchResizerClass} fc-monarch-1V6 fc-monarch-F99`;
    const tallDayCellBottomClass = "fc-monarch-jgW";
    const getShortDayCellBottomClass = (info) => joinClassNames(!info.isNarrow && "fc-monarch-toR");
    const dayRowCommonClasses = {
        /* Day Row > List-Item Event
        ----------------------------------------------------------------------------------------------- */
        listItemEventClass: (info) => joinClassNames("fc-monarch-Ika fc-monarch-7A6 fc-monarch-Fvv", info.isNarrow ? "fc-monarch-148" : "fc-monarch-cKZ"),
        listItemEventBeforeClass: (info) => joinClassNames("fc-monarch-5JF", info.isNarrow ? "fc-monarch-Jzj" : "fc-monarch-Wga"),
        listItemEventInnerClass: (info) => (info.isNarrow
            ? `fc-monarch-z5u ${xxsTextClass}`
            : "fc-monarch-2rx fc-monarch-a3B"),
        listItemEventTimeClass: (info) => joinClassNames(info.isNarrow ? "fc-monarch-a7i" : "fc-monarch-C2j", "fc-monarch-TZ4 fc-monarch-pKG fc-monarch-1Zl"),
        listItemEventTitleClass: (info) => joinClassNames(info.isNarrow ? "fc-monarch-oQ2" : "fc-monarch-aCI", "fc-monarch-DIS fc-monarch-TZ4 fc-monarch-pKG fc-monarch-OLq"),
        /* Day Row > Row Event
        ----------------------------------------------------------------------------------------------- */
        rowEventClass: (info) => joinClassNames(info.isStart && "fc-monarch-qvL", info.isEnd && "fc-monarch-9hC"),
        rowEventInnerClass: (info) => info.isNarrow ? "fc-monarch-z5u" : "fc-monarch-2rx",
        /* Day Row > More-Link
        ----------------------------------------------------------------------------------------------- */
        rowMoreLinkClass: (info) => joinClassNames("fc-monarch-Ika fc-monarch-wsy fc-monarch-Fvv", info.isNarrow
            ? "fc-monarch-148 fc-monarch-yF0"
            : "fc-monarch-cKZ fc-monarch-d0j", mutedHoverPressableClass),
        rowMoreLinkInnerClass: (info) => (info.isNarrow
            ? `fc-monarch-oQ2 fc-monarch-z5u ${xxsTextClass}`
            : "fc-monarch-aCI fc-monarch-2rx fc-monarch-a3B"),
    };
    const resourceDayHeaderClasses = {
        dayHeaderInnerClass: "fc-monarch-vUo",
        dayHeaderDividerClass: "fc-monarch-zi1 fc-monarch-jdl",
    };
    var index = {
        name: "theme-monarch",
        optionDefaults: {
            className: "fc-monarch-PVh fc-monarch-n5m",
            viewClass: (info) => {
                const hasBorderTop = !info.options.headerToolbar && !info.borderlessTop;
                const hasBorderBottom = !info.options.footerToolbar && !info.borderlessBottom;
                const hasBorderX = !info.borderlessX;
                return joinClassNames("fc-monarch-MAH fc-monarch-jdl", hasBorderTop && "fc-monarch-ku3", hasBorderBottom && "fc-monarch-zi1", hasBorderX && "fc-monarch-1Wx", (hasBorderTop && hasBorderX) && "fc-monarch-9hN", (hasBorderBottom && hasBorderX) && "fc-monarch-KxZ", !info.isHeightAuto && "fc-monarch-pKG");
            },
            /* Toolbar
            --------------------------------------------------------------------------------------------- */
            toolbarClass: (info) => joinClassNames("fc-monarch-lqx fc-monarch-dl1 fc-monarch-1sP fc-monarch-dNl fc-monarch-XpK fc-monarch-N2M fc-monarch-wwb", "fc-monarch-MAH fc-monarch-jdl", !info.borderlessX && "fc-monarch-1Wx"),
            headerToolbarClass: (info) => joinClassNames(!info.borderlessTop && "fc-monarch-ku3", !(info.borderlessTop || info.borderlessX) && "fc-monarch-9hN"),
            footerToolbarClass: (info) => joinClassNames(!info.borderlessBottom && "fc-monarch-zi1", !(info.borderlessBottom || info.borderlessX) && "fc-monarch-KxZ"),
            toolbarSectionClass: "fc-monarch-yi0 fc-monarch-dl1 fc-monarch-1sP fc-monarch-XpK fc-monarch-wwb",
            toolbarTitleClass: "fc-monarch-AVD fc-monarch-DIS",
            buttonGroupClass: (info) => joinClassNames("fc-monarch-AAA fc-monarch-dl1 fc-monarch-1sP fc-monarch-XpK", info.hasSelection && "fc-monarch-wsy fc-monarch-jdl"),
            buttonClass: (info) => joinClassNames("fc-monarch-8cT fc-monarch-AAA fc-monarch-dl1 fc-monarch-1sP fc-monarch-XpK fc-monarch-9yp fc-monarch-Z9U", info.isIconOnly ? "fc-monarch-Eaq" : "fc-monarch-5su", info.buttonGroup?.hasSelection && "fc-monarch-CH7", (info.isIconOnly || (info.buttonGroup?.hasSelection && !info.isSelected))
                ? unselectedButtonClass
                : info.isSelected
                    ? selectedButtonClass
                    : info.isPrimary
                        ? primaryButtonClass
                        : secondaryButtonClass),
            buttons: {
                prev: {
                    iconContent: () => chevronDown(joinClassNames(secondaryButtonIconClass, "fc-monarch-z44 fc-monarch-keW"))
                },
                next: {
                    iconContent: () => chevronDown(joinClassNames(secondaryButtonIconClass, "fc-monarch-KxI fc-monarch-ZW3"))
                },
                prevYear: {
                    iconContent: () => chevronDoubleLeft(joinClassNames(secondaryButtonIconClass, "fc-monarch-asP"))
                },
                nextYear: {
                    iconContent: () => chevronDoubleLeft(joinClassNames(secondaryButtonIconClass, "fc-monarch-jmT fc-monarch-jY6"))
                },
            },
            /* Abstract Event
            --------------------------------------------------------------------------------------------- */
            eventShortHeight: 50,
            eventColor: "var(--fc-monarch-event)",
            eventContrastColor: "var(--fc-monarch-event-contrast)",
            eventClass: (info) => joinClassNames(info.isDragging && "fc-monarch-n5m", info.event.url && "fc-monarch-JiE", info.isSelected
                ? joinClassNames(outlineWidthClass, info.isDragging ? "fc-monarch-1kP" : "fc-monarch-tkw")
                : outlineWidthFocusClass, outlineColorClass),
            /* Background Event
            --------------------------------------------------------------------------------------------- */
            backgroundEventColor: "var(--fc-monarch-tertiary)",
            backgroundEventClass: "fc-monarch-gTC fc-monarch-jsy fc-monarch-DO7",
            backgroundEventTitleClass: (info) => joinClassNames("fc-monarch-lMo fc-monarch-L1Y", info.isNarrow
                ? `fc-monarch-aCI fc-monarch-End ${xxsTextClass}`
                : "fc-monarch-Nca fc-monarch-8cT fc-monarch-a3B"),
            /* List-Item Event
            --------------------------------------------------------------------------------------------- */
            listItemEventClass: (info) => joinClassNames("fc-monarch-XpK", info.isSelected
                ? "fc-monarch-4Xm"
                : info.isInteractive
                    ? mutedHoverPressableClass
                    : mutedHoverClass),
            listItemEventBeforeClass: "fc-monarch-AAA fc-monarch-lNM",
            listItemEventInnerClass: "fc-monarch-PVh fc-monarch-dl1 fc-monarch-1sP fc-monarch-XpK",
            /* Block Event
            --------------------------------------------------------------------------------------------- */
            blockEventClass: (info) => joinClassNames("fc-monarch-bCs fc-monarch-eYX fc-monarch-d0j fc-monarch-DO7 fc-monarch-YjJ fc-monarch-Frw fc-monarch-vwH", info.isInteractive && "fc-monarch-st8", (!info.isSelected && info.isDragging) && "fc-monarch-iTG"),
            blockEventInnerClass: "fc-monarch-i9F fc-monarch-cfp",
            blockEventTimeClass: "fc-monarch-TZ4 fc-monarch-pKG",
            blockEventTitleClass: "fc-monarch-TZ4 fc-monarch-pKG",
            /* Row Event
            --------------------------------------------------------------------------------------------- */
            rowEventClass: (info) => joinClassNames("fc-monarch-Ika fc-monarch-JIC", info.isStart ? "fc-monarch-3J4 fc-monarch-kmj" : (!info.isNarrow && "fc-monarch-Mde"), info.isEnd ? "fc-monarch-USt fc-monarch-Skl" : (!info.isNarrow && "fc-monarch-FvP")),
            rowEventBeforeClass: (info) => joinClassNames(info.isStartResizable ? joinClassNames(info.isSelected ? rowTouchResizerClass : rowPointerResizerClass, "fc-monarch-11a") : (!info.isStart && !info.isNarrow) && "fc-monarch-1EY fc-monarch-0fm fc-monarch-hza fc-monarch-DGM fc-monarch-Uvq"),
            rowEventBeforeContent: (info) => ((!info.isStart && !info.isNarrow) ? filledRightTriangle("fc-monarch-Pwv fc-monarch-jmT fc-monarch-jY6 fc-monarch-Mra") : u(S, {})),
            rowEventAfterClass: (info) => joinClassNames(info.isEndResizable ? joinClassNames(info.isSelected ? rowTouchResizerClass : rowPointerResizerClass, "fc-monarch-Tuc") : (!info.isEnd && !info.isNarrow) && "fc-monarch-1EY fc-monarch-eDA fc-monarch-hza fc-monarch-DGM fc-monarch-Uvq"),
            rowEventAfterContent: (info) => ((!info.isEnd && !info.isNarrow) ? filledRightTriangle("fc-monarch-Pwv fc-monarch-asP fc-monarch-Mra") : u(S, {})),
            rowEventInnerClass: (info) => joinClassNames("fc-monarch-dl1 fc-monarch-1sP fc-monarch-XpK", info.isNarrow ? xxsTextClass : "fc-monarch-a3B"),
            rowEventTimeClass: (info) => joinClassNames("fc-monarch-DIS fc-monarch-1Zl", info.isNarrow ? "fc-monarch-a7i" : "fc-monarch-C2j"),
            rowEventTitleClass: (info) => joinClassNames("fc-monarch-OLq", info.isNarrow ? "fc-monarch-oQ2" : "fc-monarch-aCI"),
            /* Column Event
            --------------------------------------------------------------------------------------------- */
            columnEventTitleSticky: false,
            columnEventClass: (info) => joinClassNames(`fc-monarch-1Wx fc-monarch-A3h fc-monarch-SC5`, info.isStart && "fc-monarch-ku3 fc-monarch-Z7Q", info.isEnd && "fc-monarch-Ika fc-monarch-zi1 fc-monarch-2qh"),
            columnEventBeforeClass: (info) => joinClassNames(info.isStartResizable && joinClassNames(info.isSelected ? columnTouchResizerClass : columnPointerResizerClass, "fc-monarch-YDC")),
            columnEventAfterClass: (info) => joinClassNames(info.isEndResizable && joinClassNames(info.isSelected ? columnTouchResizerClass : columnPointerResizerClass, "fc-monarch-fJL")),
            columnEventInnerClass: (info) => joinClassNames("fc-monarch-dl1", info.isShort
                ? "fc-monarch-1sP fc-monarch-XpK fc-monarch-iS4 fc-monarch-NWN"
                : joinClassNames("fc-monarch-sgX", info.isNarrow ? "fc-monarch-aCI fc-monarch-2rx" : "fc-monarch-Nca fc-monarch-Jhn"), (info.isShort || info.isNarrow) ? xxsTextClass : "fc-monarch-a3B"),
            columnEventTimeClass: (info) => joinClassNames("fc-monarch-NPw fc-monarch-OLq", !info.isShort && (info.isNarrow ? "fc-monarch-8ub" : "fc-monarch-x96")),
            columnEventTitleClass: (info) => joinClassNames("fc-monarch-1Zl", !info.isShort && (info.isNarrow ? "fc-monarch-2rx" : "fc-monarch-Jhn")),
            /* More-Link
            --------------------------------------------------------------------------------------------- */
            moreLinkClass: `${outlineWidthFocusClass} ${outlineColorClass}`,
            moreLinkInnerClass: "fc-monarch-TZ4 fc-monarch-pKG",
            columnMoreLinkClass: `fc-monarch-Ika fc-monarch-wsy fc-monarch-d0j fc-monarch-4MR fc-monarch-Fvv ${strongSolidPressableClass} fc-monarch-vwH fc-monarch-A3h fc-monarch-SC5`,
            columnMoreLinkInnerClass: (info) => (info.isNarrow
                ? `fc-monarch-KUX ${xxsTextClass}`
                : "fc-monarch-iS4 fc-monarch-a3B"),
            /* Day Header
            --------------------------------------------------------------------------------------------- */
            dayHeaderAlign: "center",
            dayHeaderClass: (info) => joinClassNames("fc-monarch-E9P", info.isMajor && "fc-monarch-wsy fc-monarch-zOd", (info.isDisabled && !info.inPopover) && "fc-monarch-VTw"),
            dayHeaderInnerClass: "fc-monarch-bCs fc-monarch-9Rk fc-monarch-fn8 fc-monarch-dl1 fc-monarch-sgX fc-monarch-XpK fc-monarch-hS8",
            dayHeaderContent: (info) => (u(S, { children: [info.weekdayText && (u("div", { className: "fc-monarch-a3B fc-monarch-XHd fc-monarch-JMv", children: info.weekdayText })), info.dayNumberText && (u("div", { className: joinClassNames("fc-monarch-XE0 fc-monarch-AAA fc-monarch-dl1 fc-monarch-1sP fc-monarch-XpK fc-monarch-E9P", info.isNarrow
                            ? "fc-monarch-IY5 fc-monarch-1Po"
                            : "fc-monarch-n6w fc-monarch-9ZS", info.isToday
                            ? (info.hasNavLink ? tertiaryPressableGroupClass : tertiaryClass)
                            : (info.hasNavLink && mutedHoverPressableGroupClass), info.hasNavLink && `${outlineWidthGroupFocusClass} ${outlineColorClass}`), children: info.dayNumberText }))] })),
            /* Day Cell
            --------------------------------------------------------------------------------------------- */
            dayCellClass: (info) => joinClassNames("fc-monarch-wsy", info.isMajor ? "fc-monarch-zOd" : "fc-monarch-jdl", info.isDisabled && "fc-monarch-VTw"),
            dayCellTopClass: (info) => joinClassNames("fc-monarch-dl1 fc-monarch-1sP", info.isNarrow
                ? "fc-monarch-LMv fc-monarch-toR"
                : "fc-monarch-E9P fc-monarch-84e"),
            dayCellTopInnerClass: (info) => joinClassNames("fc-monarch-dl1 fc-monarch-1sP fc-monarch-XpK fc-monarch-E9P fc-monarch-TZ4 fc-monarch-AAA", info.isNarrow
                ? `fc-monarch-TT0 fc-monarch-oM6 ${xxsTextClass}`
                : "fc-monarch-cGD fc-monarch-TFV fc-monarch-9yp", info.text === info.dayNumberText
                ? (info.isNarrow ? "fc-monarch-79F" : "fc-monarch-ilz")
                : (info.isNarrow ? "fc-monarch-aCI" : "fc-monarch-Nca"), info.isToday
                ? (info.hasNavLink ? tertiaryPressableClass : tertiaryClass)
                : (info.hasNavLink && mutedHoverPressableClass), info.isOther && "fc-monarch-bZ0", info.monthText && "fc-monarch-DIS"),
            dayCellInnerClass: (info) => joinClassNames(info.inPopover && "fc-monarch-3N5"),
            /* Popover
            --------------------------------------------------------------------------------------------- */
            popoverFormat: { day: "numeric", weekday: "short" },
            popoverClass: "fc-monarch-wsy fc-monarch-jdl fc-monarch-hny fc-monarch-pKG fc-monarch-bvX fc-monarch-9Mx fc-monarch-sT2 fc-monarch-1kP fc-monarch-xcS fc-monarch-n5m",
            popoverCloseClass: `fc-monarch-bCs fc-monarch-1EY fc-monarch-SKv fc-monarch-aYN fc-monarch-n6w fc-monarch-AAA fc-monarch-XpK fc-monarch-E9P ${mutedHoverPressableClass} ${outlineWidthFocusClass} ${outlineColorClass} fc-monarch-Z9U`,
            popoverCloseContent: () => x(`fc-monarch-XUJ ${mutedFgPressableGroupClass}`),
            /* Lane
            --------------------------------------------------------------------------------------------- */
            dayLaneClass: (info) => joinClassNames("fc-monarch-wsy", info.isMajor ? "fc-monarch-zOd" : "fc-monarch-jdl", info.isDisabled && "fc-monarch-VTw"),
            dayLaneInnerClass: (info) => (info.isStack
                ? "fc-monarch-gMS"
                : info.isNarrow ? "fc-monarch-148" : "fc-monarch-Jzj fc-monarch-B3G"),
            slotLaneClass: (info) => joinClassNames("fc-monarch-wsy fc-monarch-jdl", info.isMinor && "fc-monarch-TN2"),
            /* List Day
            --------------------------------------------------------------------------------------------- */
            listDayFormat: { day: "numeric" },
            listDayAltFormat: { month: "short", weekday: "short", forceCommas: true },
            listDayClass: (info) => joinClassNames(!info.isLast && "fc-monarch-zi1 fc-monarch-jdl", "fc-monarch-dl1 fc-monarch-1sP fc-monarch-EF4"),
            listDayHeaderClass: "fc-monarch-3N5 fc-monarch-yi0 fc-monarch-CjM fc-monarch-0i7 fc-monarch-l5b fc-monarch-dl1 fc-monarch-1sP fc-monarch-XpK fc-monarch-tgZ",
            listDayHeaderInnerClass: (info) => (!info.level
                ? joinClassNames("fc-monarch-46Q fc-monarch-AAA fc-monarch-dl1 fc-monarch-1sP fc-monarch-XpK fc-monarch-9ZS", info.text === info.dayNumberText
                    ? "fc-monarch-Dhe fc-monarch-E9P"
                    : "fc-monarch-Apf", info.isToday
                    ? (info.hasNavLink ? tertiaryPressableClass : tertiaryClass)
                    : (info.hasNavLink && mutedHoverPressableClass))
                : joinClassNames("fc-monarch-a3B fc-monarch-XHd", info.hasNavLink && "fc-monarch-Eu0")),
            listDayBodyClass: "fc-monarch-1El fc-monarch-2KU fc-monarch-dl6 fc-monarch-NWN",
            /* Single Month (in Multi-Month)
            --------------------------------------------------------------------------------------------- */
            singleMonthClass: (info) => joinClassNames(info.multiMonthColumns > 1 && "fc-monarch-jD5", (info.multiMonthColumns === 1 && !info.isLast) &&
                "fc-monarch-jdl fc-monarch-zi1"),
            singleMonthHeaderClass: (info) => joinClassNames(info.multiMonthColumns > 1
                ? "fc-monarch-lTO"
                : "fc-monarch-Jhn fc-monarch-zi1 fc-monarch-jdl fc-monarch-MAH", "fc-monarch-XpK"),
            singleMonthHeaderInnerClass: (info) => joinClassNames("fc-monarch-Apf fc-monarch-Jhn fc-monarch-AAA fc-monarch-1Po fc-monarch-DIS", info.hasNavLink && mutedHoverPressableClass),
            /* Misc Table
            --------------------------------------------------------------------------------------------- */
            tableHeaderClass: "fc-monarch-MAH",
            fillerClass: (info) => joinClassNames("fc-monarch-lMo fc-monarch-wsy", info.inTableHeader ? "fc-monarch-d0j" : "fc-monarch-jdl"),
            dayNarrowWidth: 100,
            dayHeaderRowClass: "fc-monarch-wsy fc-monarch-jdl",
            dayRowClass: "fc-monarch-wsy fc-monarch-jdl",
            /* Misc Content
            --------------------------------------------------------------------------------------------- */
            navLinkClass: `${outlineWidthFocusClass} ${outlineColorClass}`,
            inlineWeekNumberClass: (info) => joinClassNames("fc-monarch-1EY fc-monarch-dl1 fc-monarch-1sP fc-monarch-XpK fc-monarch-TZ4", info.isNarrow
                ? `fc-monarch-2ik fc-monarch-rbS fc-monarch-SEP fc-monarch-9ml fc-monarch-Utm fc-monarch-dOc ${xxsTextClass}`
                : "fc-monarch-1b8 fc-monarch-tHH fc-monarch-TFV fc-monarch-Nca fc-monarch-AAA fc-monarch-9yp", info.hasNavLink
                ? secondaryPressableClass
                : secondaryClass),
            nonBusinessHoursClass: "fc-monarch-VTw",
            highlightClass: "fc-monarch-PHz",
            nowIndicatorLineClass: "fc-monarch-CH7 fc-monarch-qQW fc-monarch-nKh",
            nowIndicatorDotClass: "fc-monarch-aAW fc-monarch-Vpk fc-monarch-nKh fc-monarch-63n fc-monarch-AAA fc-monarch-GBJ fc-monarch-SC5",
            /* Resource Day Header
            --------------------------------------------------------------------------------------------- */
            resourceDayHeaderAlign: "center",
            resourceDayHeaderClass: (info) => joinClassNames("fc-monarch-wsy", info.isMajor ? "fc-monarch-zOd" : "fc-monarch-jdl"),
            resourceDayHeaderInnerClass: (info) => joinClassNames("fc-monarch-bvX fc-monarch-dl1 fc-monarch-sgX", info.isNarrow ? "fc-monarch-a3B" : "fc-monarch-9yp"),
            /* Resource Data Grid
            --------------------------------------------------------------------------------------------- */
            resourceColumnHeaderClass: "fc-monarch-wsy fc-monarch-jdl fc-monarch-E9P",
            resourceColumnHeaderInnerClass: "fc-monarch-bvX fc-monarch-9yp",
            resourceColumnResizerClass: "fc-monarch-1EY fc-monarch-AWB fc-monarch-4Tv fc-monarch-dnf",
            resourceGroupHeaderClass: "fc-monarch-wsy fc-monarch-jdl fc-monarch-VTw",
            resourceGroupHeaderInnerClass: "fc-monarch-bvX fc-monarch-9yp",
            resourceCellClass: "fc-monarch-wsy fc-monarch-jdl",
            resourceCellInnerClass: "fc-monarch-bvX fc-monarch-9yp",
            resourceIndentClass: "fc-monarch-Wga fc-monarch-p9t fc-monarch-E9P",
            resourceExpanderClass: `fc-monarch-bCs fc-monarch-iS4 fc-monarch-AAA ${mutedHoverPressableClass} ${outlineWidthFocusClass} ${outlineColorClass}`,
            resourceExpanderContent: (info) => chevronDown(joinClassNames(`fc-monarch-vnf ${mutedFgPressableGroupClass}`, !info.isExpanded && "fc-monarch-KxI fc-monarch-ZW3")),
            resourceHeaderRowClass: "fc-monarch-wsy fc-monarch-jdl",
            resourceRowClass: "fc-monarch-wsy fc-monarch-jdl",
            resourceColumnDividerClass: "fc-monarch-USt fc-monarch-zOd",
            /* Timeline Lane
            --------------------------------------------------------------------------------------------- */
            resourceGroupLaneClass: "fc-monarch-wsy fc-monarch-jdl fc-monarch-VTw",
            resourceLaneClass: "fc-monarch-wsy fc-monarch-jdl",
            resourceLaneBottomClass: (info) => info.options.eventOverlap && "fc-monarch-uuA",
            timelineBottomClass: "fc-monarch-uuA",
        },
        views: {
            dayGrid: {
                ...dayRowCommonClasses,
                dayCellBottomClass: getShortDayCellBottomClass,
            },
            multiMonth: {
                ...dayRowCommonClasses,
                dayCellBottomClass: getShortDayCellBottomClass,
                dayHeaderInnerClass: (info) => !info.inPopover && "fc-monarch-ohi",
                dayHeaderDividerClass: (info) => joinClassNames(info.multiMonthColumns === 1 &&
                    "fc-monarch-zi1 fc-monarch-jdl"),
                tableBodyClass: (info) => joinClassNames(info.multiMonthColumns > 1 &&
                    "fc-monarch-wsy fc-monarch-jdl fc-monarch-Fvv fc-monarch-pKG"),
            },
            timeGrid: {
                ...dayRowCommonClasses,
                dayCellBottomClass: tallDayCellBottomClass,
                /* TimeGrid > Week Number Header
                ------------------------------------------------------------------------------------------- */
                weekNumberHeaderClass: "fc-monarch-XpK fc-monarch-LMv",
                weekNumberHeaderInnerClass: (info) => joinClassNames("fc-monarch-Wga fc-monarch-2tF fc-monarch-dl1 fc-monarch-1sP fc-monarch-XpK fc-monarch-AAA", info.options.dayMinWidth !== undefined && "fc-monarch-KYn", info.isNarrow
                    ? "fc-monarch-oM6 fc-monarch-ZrE fc-monarch-a3B"
                    : "fc-monarch-TFV fc-monarch-Nca fc-monarch-9yp", info.hasNavLink
                    ? secondaryPressableClass
                    : secondaryClass),
                /* TimeGrid > All-Day Header
                ------------------------------------------------------------------------------------------- */
                allDayHeaderClass: "fc-monarch-XpK fc-monarch-LMv",
                allDayHeaderInnerClass: (info) => joinClassNames("fc-monarch-bvX fc-monarch-2HE", info.isNarrow ? xxsTextClass : "fc-monarch-9yp"),
                allDayDividerClass: "fc-monarch-zi1 fc-monarch-jdl",
                /* TimeGrid > Slot Header
                ------------------------------------------------------------------------------------------- */
                slotHeaderClass: (info) => joinClassNames("fc-monarch-hza fc-monarch-OBr fc-monarch-LMv fc-monarch-wsy fc-monarch-jdl", info.isMinor && "fc-monarch-TN2"),
                slotHeaderInnerClass: (info) => joinClassNames("fc-monarch-eYX fc-monarch-Mde fc-monarch-OQ9 fc-monarch-2tF", info.isNarrow
                    ? `fc-monarch-uqG ${xxsTextClass}`
                    : "fc-monarch-Fn5 fc-monarch-9yp", info.isFirst && "fc-monarch-pps"),
                slotHeaderDividerClass: (info) => joinClassNames("fc-monarch-USt", (info.inTableHeader && info.options.dayMinWidth === undefined)
                    ? "fc-monarch-d0j"
                    : "fc-monarch-jdl"),
            },
            list: {
                /* List-View > List-Item Event
                ------------------------------------------------------------------------------------------- */
                listItemEventClass: "fc-monarch-bCs fc-monarch-3N5 fc-monarch-NYF fc-monarch-tgZ",
                listItemEventBeforeClass: "fc-monarch-fn8 fc-monarch-yDA",
                listItemEventInnerClass: "fc-monarch-tgZ fc-monarch-9yp",
                listItemEventTimeClass: "fc-monarch-yi0 fc-monarch-roZ fc-monarch-aHX fc-monarch-TZ4 fc-monarch-pKG fc-monarch-IPx",
                listItemEventTitleClass: (info) => joinClassNames("fc-monarch-1El fc-monarch-2KU fc-monarch-TZ4 fc-monarch-pKG", info.event.url && "fc-monarch-Ogp"),
                /* No-Events Screen
                ------------------------------------------------------------------------------------------- */
                noEventsClass: "fc-monarch-1El fc-monarch-dl1 fc-monarch-sgX fc-monarch-XpK fc-monarch-E9P",
                noEventsInnerClass: "fc-monarch-P9h",
            },
            resourceTimeGrid: resourceDayHeaderClasses,
            resourceDayGrid: resourceDayHeaderClasses,
            timeline: {
                /* Timeline > Row Event
                ------------------------------------------------------------------------------------------- */
                rowEventClass: (info) => joinClassNames(info.isEnd && "fc-monarch-9hC"),
                rowEventInnerClass: (info) => info.options.eventOverlap ? "fc-monarch-Jhn" : "fc-monarch-dl6",
                /* Timeline > More-Link
                ------------------------------------------------------------------------------------------- */
                rowMoreLinkClass: `fc-monarch-9hC fc-monarch-Ika fc-monarch-Fvv fc-monarch-wsy fc-monarch-d0j fc-monarch-4MR ${strongSolidPressableClass} fc-monarch-vwH`,
                rowMoreLinkInnerClass: "fc-monarch-iS4 fc-monarch-a3B",
                /* Timeline > Slot Header
                ------------------------------------------------------------------------------------------- */
                slotHeaderSticky: "0.5rem",
                slotHeaderAlign: (info) => ((info.level || info.isTime)
                    ? "start"
                    : "center"),
                slotHeaderClass: (info) => joinClassNames("fc-monarch-wsy", info.level
                    ? "fc-monarch-d0j fc-monarch-Bsl"
                    : joinClassNames("fc-monarch-jdl", info.isTime
                        ? "fc-monarch-uuA fc-monarch-OBr fc-monarch-LMv"
                        : "fc-monarch-E9P")),
                slotHeaderInnerClass: (info) => joinClassNames("fc-monarch-9yp", info.level
                    ? joinClassNames("fc-monarch-cJ3 fc-monarch-Nca fc-monarch-Jhn fc-monarch-AAA", info.hasNavLink
                        ? secondaryPressableClass
                        : secondaryClass)
                    : joinClassNames("fc-monarch-Nca", info.isTime
                        ? joinClassNames("fc-monarch-b0n fc-monarch-eYX fc-monarch-4oC", info.isFirst && "fc-monarch-pps")
                        : "fc-monarch-dl6", info.hasNavLink && "fc-monarch-Eu0")),
                slotHeaderDividerClass: "fc-monarch-zi1 fc-monarch-jdl",
            },
        }
    };
    /* SVGs
    ------------------------------------------------------------------------------------------------- */
    function chevronDown(className) {
        return u("svg", { xmlns: "http://www.w3.org/2000/svg", className: className, width: "20", height: "20", viewBox: "80 -880 800 800", fill: "currentColor", children: u("path", { d: "M480-304 240-544l56-56 184 184 184-184 56 56-240 240Z" }) });
    }
    function chevronDoubleLeft(className) {
        return u("svg", { xmlns: "http://www.w3.org/2000/svg", className: className, width: "20", height: "20", viewBox: "80 -880 800 800", fill: "currentColor", children: u("path", { d: "M440-240 200-480l240-240 56 56-183 184 183 184-56 56Zm264 0L464-480l240-240 56 56-183 184 183 184-56 56Z" }) });
    }
    function x(className) {
        return u("svg", { xmlns: "http://www.w3.org/2000/svg", className: className, width: "20", height: "20", viewBox: "80 -880 800 800", fill: "currentColor", children: u("path", { d: "m256-200-56-56 224-224-224-224 56-56 224 224 224-224 56 56-224 224 224 224-56 56-224-224-224 224Z" }) });
    }
    function filledRightTriangle(className) {
        return (u("svg", { xmlns: "http://www.w3.org/2000/svg", viewBox: "0 0 800 2200", preserveAspectRatio: "none", className: className, children: u("polygon", { points: "0,0 66,0 800,1100 66,2200 0,2200", fill: "currentColor" }) }));
    }

    globalPlugins.push(index);

})(FullCalendar.Shared);
