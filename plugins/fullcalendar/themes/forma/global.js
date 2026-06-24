/*!
FullCalendar (Vanilla JS) v7.0.0
Docs & License: https://fullcalendar.io
(c) 2026 Adam Shaw
*/
(function ({ H: joinClassNames, u, S, G: globalPlugins }) {
    

    // usually 11px font / 12px line-height
    const xxsTextClass = "fc-forma-vQz";
    // outline
    const outlineWidthClass = "fc-forma-0Bj";
    const outlineWidthFocusClass = "fc-forma-uqo";
    const outlineOffsetClass = "fc-forma-3Xj";
    const outlineInsetClass = "fc-forma-fFh";
    const primaryOutlineColorClass = "fc-forma-Fmy";
    const primaryOutlineFocusClass = `${outlineWidthFocusClass} ${primaryOutlineColorClass}`;
    // neutral buttons
    const strongSolidPressableClass = joinClassNames("fc-forma-mr2", "fc-forma-Vyz", "fc-forma-pgt");
    const mutedPressableClass = `fc-forma-Wv4 fc-forma-mcn fc-forma-0sn ${primaryOutlineFocusClass}`;
    const mutedHoverClass = "fc-forma-ShG";
    const mutedHoverPressableClass = `${mutedHoverClass} fc-forma-nge fc-forma-rKU`;
    const mutedHoverButtonClass = `${mutedHoverPressableClass} fc-forma-wsy fc-forma-d0j ${primaryOutlineFocusClass}`;
    // controls
    const unselectedPressableClass = mutedHoverPressableClass;
    const unselectedButtonClass = `${unselectedPressableClass} fc-forma-wsy fc-forma-d0j ${primaryOutlineFocusClass}`;
    const selectedButtonClass = `fc-forma-Wv4 fc-forma-wsy fc-forma-BST ${primaryOutlineFocusClass} fc-forma-07j`;
    // primary
    const primaryClass = "fc-forma-7I6 fc-forma-TSf";
    const primaryPressableClass = `${primaryClass} fc-forma-2Qu fc-forma-lgp fc-forma-PQx`;
    const primaryButtonClass = `${primaryPressableClass} fc-forma-wsy fc-forma-d0j ${primaryOutlineFocusClass} ${outlineOffsetClass}`;
    // secondary
    const secondaryButtonClass = `${mutedHoverPressableClass} fc-forma-wsy fc-forma-tfB fc-forma-cBw ${primaryOutlineFocusClass}`;
    const secondaryButtonIconClass = "fc-forma-XUJ";
    // event content
    const eventMutedBgClass = "fc-forma-i6P";
    const eventMutedPressableClass = joinClassNames(eventMutedBgClass, "fc-forma-SWh", "fc-forma-RHy");
    const eventFaintBgClass = "fc-forma-lrD";
    const eventFaintPressableClass = joinClassNames(eventFaintBgClass, "fc-forma-rom", "fc-forma-DUE");
    // interactive neutral foregrounds
    const mutedFgPressableGroupClass = "fc-forma-V1v fc-forma-sS4 fc-forma-Di9";
    // transparent resizer for mouse
    const blockPointerResizerClass = "fc-forma-1EY fc-forma-pps fc-forma-vs6";
    const rowPointerResizerClass = `${blockPointerResizerClass} fc-forma-AWB fc-forma-hza`;
    const columnPointerResizerClass = `${blockPointerResizerClass} fc-forma-MaV fc-forma-uuA`;
    // circle resizer for touch
    const blockTouchResizerClass = "fc-forma-1EY fc-forma-3wQ fc-forma-wsy fc-forma-lNM fc-forma-AAA fc-forma-RJG";
    const rowTouchResizerClass = `${blockTouchResizerClass} fc-forma-ERR fc-forma-Dq8`;
    const columnTouchResizerClass = `${blockTouchResizerClass} fc-forma-1V6 fc-forma-F99`;
    const tallDayCellBottomClass = "fc-forma-jgW";
    const getShortDayCellBottomClass = (info) => joinClassNames(!info.isNarrow && "fc-forma-toR");
    const getSlotClass = (info) => joinClassNames("fc-forma-wsy fc-forma-tfB", info.isMinor && "fc-forma-TN2");
    const dayRowCommonClasses = {
        /* Day Row > List-Item Event
        ----------------------------------------------------------------------------------------------- */
        listItemEventClass: (info) => joinClassNames("fc-forma-Ika fc-forma-7A6 fc-forma-Fvv", info.isNarrow ? "fc-forma-148" : "fc-forma-cKZ", info.isSelected
            ? "fc-forma-Wv4"
            : info.isInteractive
                ? mutedHoverPressableClass
                : mutedHoverClass),
        listItemEventBeforeClass: (info) => joinClassNames("fc-forma-5JF fc-forma-lNM fc-forma-AAA", info.isNarrow ? "fc-forma-Jzj" : "fc-forma-Wga"),
        listItemEventInnerClass: (info) => (info.isNarrow
            ? `fc-forma-z5u ${xxsTextClass}`
            : "fc-forma-2rx fc-forma-a3B"),
        listItemEventTimeClass: (info) => joinClassNames(info.isNarrow ? "fc-forma-a7i" : "fc-forma-C2j", "fc-forma-TZ4 fc-forma-pKG fc-forma-1Zl"),
        listItemEventTitleClass: (info) => joinClassNames(info.isNarrow ? "fc-forma-oQ2" : "fc-forma-aCI", "fc-forma-DIS fc-forma-TZ4 fc-forma-pKG fc-forma-OLq"),
        /* Day Row > Row Event
        ----------------------------------------------------------------------------------------------- */
        rowEventClass: (info) => joinClassNames(info.isEnd && (info.isNarrow ? "fc-forma-9hC" : "fc-forma-3e1")),
        rowEventInnerClass: (info) => info.isNarrow ? "fc-forma-z5u" : "fc-forma-2rx",
        /* Day Row > More-Link
        ----------------------------------------------------------------------------------------------- */
        rowMoreLinkClass: (info) => joinClassNames("fc-forma-Ika fc-forma-wsy fc-forma-Fvv", info.isNarrow
            ? "fc-forma-148 fc-forma-Baf"
            : "fc-forma-cKZ fc-forma-d0j fc-forma-sI7", mutedHoverPressableClass),
        rowMoreLinkInnerClass: (info) => (info.isNarrow
            ? `fc-forma-oQ2 fc-forma-z5u ${xxsTextClass}`
            : "fc-forma-aCI fc-forma-2rx fc-forma-a3B"),
    };
    var index = {
        name: "theme-forma",
        optionDefaults: {
            className: (info) => joinClassNames("fc-forma-b7K fc-forma-n5m", !(info.borderlessTop || info.borderlessBottom || info.borderlessX) && "fc-forma-Fvv fc-forma-eSM"),
            viewClass: (info) => {
                const hasBorderTop = !info.options.headerToolbar && !info.borderlessTop;
                const hasBorderBottom = !info.options.footerToolbar && !info.borderlessBottom;
                const hasBorderX = !info.borderlessX;
                return joinClassNames("fc-forma-RJG fc-forma-tfB", hasBorderTop && "fc-forma-ku3", hasBorderBottom && "fc-forma-zi1", hasBorderX && "fc-forma-1Wx", (hasBorderTop && hasBorderX) && "fc-forma-Z7Q", (hasBorderBottom && hasBorderX) && "fc-forma-2qh", !info.isHeightAuto && "fc-forma-pKG");
            },
            /* Toolbar
            --------------------------------------------------------------------------------------------- */
            toolbarClass: (info) => joinClassNames("fc-forma-IJJ fc-forma-dl1 fc-forma-1sP fc-forma-dNl fc-forma-XpK fc-forma-N2M fc-forma-wwb", "fc-forma-RJG fc-forma-tfB", !info.borderlessX && "fc-forma-1Wx"),
            headerToolbarClass: (info) => joinClassNames("fc-forma-zi1", !info.borderlessTop && "fc-forma-ku3", !(info.borderlessTop || info.borderlessX) && "fc-forma-Z7Q"),
            footerToolbarClass: (info) => joinClassNames("fc-forma-ku3", !info.borderlessBottom && "fc-forma-zi1", !(info.borderlessBottom || info.borderlessX) && "fc-forma-2qh"),
            toolbarSectionClass: "fc-forma-yi0 fc-forma-dl1 fc-forma-1sP fc-forma-XpK fc-forma-wwb",
            toolbarTitleClass: "fc-forma-2rA",
            buttonGroupClass: "fc-forma-dl1 fc-forma-1sP fc-forma-XpK",
            buttonClass: (info) => joinClassNames("fc-forma-bCs fc-forma-End fc-forma-Fvv fc-forma-dl1 fc-forma-1sP fc-forma-XpK fc-forma-9yp fc-forma-Z9U", info.isIconOnly ? "fc-forma-Nca" : "fc-forma-Apf", info.isIconOnly
                ? mutedHoverButtonClass
                : info.buttonGroup?.hasSelection
                    ? info.isSelected
                        ? selectedButtonClass
                        : unselectedButtonClass
                    : info.isPrimary
                        ? primaryButtonClass
                        : secondaryButtonClass),
            buttons: {
                prev: {
                    iconContent: () => chevronDown(joinClassNames(secondaryButtonIconClass, "fc-forma-z44 fc-forma-keW"))
                },
                next: {
                    iconContent: () => chevronDown(joinClassNames(secondaryButtonIconClass, "fc-forma-KxI fc-forma-ZW3"))
                },
                prevYear: {
                    iconContent: () => chevronDoubleLeft(joinClassNames(secondaryButtonIconClass, "fc-forma-asP"))
                },
                nextYear: {
                    iconContent: () => chevronDoubleLeft(joinClassNames(secondaryButtonIconClass, "fc-forma-jmT fc-forma-jY6"))
                },
            },
            /* Abstract Event
            --------------------------------------------------------------------------------------------- */
            eventShortHeight: 50,
            eventColor: "var(--fc-forma-event)",
            eventContrastColor: "var(--fc-forma-event-contrast)",
            eventClass: (info) => joinClassNames(info.isDragging && "fc-forma-n5m", info.event.url && "fc-forma-JiE", info.isSelected
                ? joinClassNames(outlineWidthClass, info.isDragging && "fc-forma-1kP")
                : outlineWidthFocusClass, primaryOutlineColorClass),
            /* Background Event
            --------------------------------------------------------------------------------------------- */
            backgroundEventColor: "var(--fc-forma-background-event)",
            backgroundEventClass: "fc-forma-gTC fc-forma-jsy fc-forma-DO7",
            backgroundEventTitleClass: (info) => joinClassNames("fc-forma-lMo fc-forma-L1Y", info.isNarrow
                ? `fc-forma-iS4 ${xxsTextClass}`
                : "fc-forma-3N5 fc-forma-a3B"),
            /* List-Item Event
            --------------------------------------------------------------------------------------------- */
            listItemEventClass: "fc-forma-XpK",
            listItemEventInnerClass: "fc-forma-b7K fc-forma-dl1 fc-forma-1sP fc-forma-XpK",
            /* Block Event
            --------------------------------------------------------------------------------------------- */
            blockEventClass: (info) => joinClassNames("fc-forma-bCs fc-forma-eYX fc-forma-lNM fc-forma-vwH", info.isInteractive
                ? eventMutedPressableClass
                : eventMutedBgClass, (info.isDragging && !info.isSelected) && "fc-forma-iTG", outlineOffsetClass),
            blockEventTimeClass: "fc-forma-TZ4 fc-forma-pKG fc-forma-1Zl",
            blockEventTitleClass: "fc-forma-TZ4 fc-forma-pKG fc-forma-OLq",
            /* Row Event
            --------------------------------------------------------------------------------------------- */
            rowEventClass: (info) => joinClassNames("fc-forma-Ika fc-forma-530 fc-forma-2dx fc-forma-XpK", info.isStart && "fc-forma-riO fc-forma-kmj", info.isEnd && "fc-forma-ZNR fc-forma-9wT fc-forma-Skl"),
            rowEventBeforeClass: (info) => joinClassNames(info.isStartResizable ? joinClassNames(info.isSelected ? rowTouchResizerClass : rowPointerResizerClass, "fc-forma-0fm") : (!info.isStart && !info.isNarrow) && joinClassNames(`fc-forma-Wga fc-forma-3wQ fc-forma-u78 fc-forma-8UH fc-forma-MlZ`, "fc-forma-QX7 fc-forma-vk6")),
            rowEventAfterClass: (info) => joinClassNames(info.isEndResizable ? joinClassNames(info.isSelected ? rowTouchResizerClass : rowPointerResizerClass, "fc-forma-Tuc") : (!info.isEnd && !info.isNarrow) && joinClassNames(`fc-forma-KYn fc-forma-3wQ fc-forma-u78 fc-forma-lzW fc-forma-MlZ`, "fc-forma-ABq fc-forma-qIw")),
            rowEventInnerClass: (info) => joinClassNames("fc-forma-dl1 fc-forma-1sP fc-forma-XpK", info.isNarrow ? xxsTextClass : "fc-forma-a3B"),
            rowEventTimeClass: (info) => joinClassNames("fc-forma-1OT", info.isNarrow ? "fc-forma-a7i" : "fc-forma-C2j"),
            rowEventTitleClass: (info) => (info.isNarrow ? "fc-forma-oQ2" : "fc-forma-aCI"),
            /* Column Event
            --------------------------------------------------------------------------------------------- */
            columnEventClass: (info) => joinClassNames("fc-forma-riO fc-forma-ZNR fc-forma-9wT fc-forma-A3h fc-forma-c3P", info.isStart && "fc-forma-jVY fc-forma-Qex fc-forma-Z7Q", info.isEnd && "fc-forma-Ika fc-forma-K3J fc-forma-wZV fc-forma-2qh"),
            columnEventBeforeClass: (info) => joinClassNames(info.isStartResizable && joinClassNames(info.isSelected ? columnTouchResizerClass : columnPointerResizerClass, "fc-forma-YDC")),
            columnEventAfterClass: (info) => joinClassNames(info.isEndResizable && joinClassNames(info.isSelected ? columnTouchResizerClass : columnPointerResizerClass, "fc-forma-fJL")),
            columnEventInnerClass: (info) => joinClassNames("fc-forma-dl1", info.isShort
                ? "fc-forma-1sP fc-forma-XpK fc-forma-iS4 fc-forma-NWN"
                : joinClassNames("fc-forma-sgX", info.isNarrow ? "fc-forma-oQ2" : "fc-forma-aCI")),
            columnEventTimeClass: (info) => joinClassNames(!info.isShort && (info.isNarrow ? "fc-forma-166" : "fc-forma-4dx"), xxsTextClass),
            columnEventTitleClass: (info) => joinClassNames(!info.isShort && (info.isNarrow ? "fc-forma-2rx" : "fc-forma-Jhn"), (info.isShort || info.isNarrow) ? xxsTextClass : "fc-forma-a3B"),
            /* More-Link
            --------------------------------------------------------------------------------------------- */
            moreLinkClass: `${outlineWidthFocusClass} ${primaryOutlineColorClass}`,
            moreLinkInnerClass: "fc-forma-TZ4 fc-forma-pKG",
            columnMoreLinkClass: `fc-forma-Ika fc-forma-wsy fc-forma-d0j fc-forma-4MR fc-forma-Fvv ${strongSolidPressableClass} fc-forma-vwH fc-forma-A3h fc-forma-c3P ${outlineOffsetClass}`,
            columnMoreLinkInnerClass: (info) => (info.isNarrow
                ? `fc-forma-KUX ${xxsTextClass}`
                : "fc-forma-iS4 fc-forma-a3B"),
            /* Day Header
            --------------------------------------------------------------------------------------------- */
            dayHeaderAlign: (info) => info.isNarrow ? "center" : "start",
            dayHeaderClass: (info) => joinClassNames("fc-forma-E9P", info.isToday && !info.level && "fc-forma-eYX", info.isDisabled && "fc-forma-zNL", info.inPopover
                ? "fc-forma-zi1 fc-forma-tfB fc-forma-zNL"
                : joinClassNames(info.isMajor ? "fc-forma-wsy fc-forma-BST" :
                    !info.isNarrow && "fc-forma-wsy fc-forma-tfB")),
            dayHeaderInnerClass: (info) => joinClassNames("fc-forma-3N5 fc-forma-dl1 fc-forma-sgX", info.isToday && info.level && "fc-forma-eYX", info.hasNavLink && `${mutedHoverPressableClass} ${outlineInsetClass}`),
            dayHeaderContent: (info) => (u(S, { children: [info.isToday && (u("div", { className: "fc-forma-1EY fc-forma-n9G fc-forma-MaV fc-forma-tb8 fc-forma-Baf fc-forma-3Lc" })), info.dayNumberText && (u("div", { className: joinClassNames(info.isToday && "fc-forma-DIS", info.isNarrow ? "fc-forma-1Po" : "fc-forma-9ZS"), children: info.dayNumberText })), info.weekdayText && (u("div", { className: "fc-forma-a3B", children: info.weekdayText }))] })),
            /* Day Cell
            --------------------------------------------------------------------------------------------- */
            dayCellClass: (info) => joinClassNames("fc-forma-wsy", info.isMajor ? "fc-forma-BST" : "fc-forma-tfB", ((info.isOther || info.isDisabled) && !info.options.businessHours) && "fc-forma-zNL"),
            dayCellTopClass: (info) => joinClassNames(info.isNarrow ? "fc-forma-toR" : "fc-forma-84e", "fc-forma-dl1 fc-forma-1sP", ((info.isOther || info.isDisabled) && info.options.businessHours) && "fc-forma-cOV"),
            dayCellTopInnerClass: (info) => joinClassNames("fc-forma-dl1 fc-forma-1sP fc-forma-XpK fc-forma-E9P fc-forma-TZ4", info.isNarrow
                ? `fc-forma-SEP fc-forma-oM6 ${xxsTextClass}`
                : "fc-forma-V9v fc-forma-TFV fc-forma-9yp", info.isToday
                ? joinClassNames("fc-forma-AAA", info.isNarrow ? "fc-forma-qvL" : "fc-forma-Wga", info.text === info.dayNumberText
                    ? (info.isNarrow ? "fc-forma-79F" : "fc-forma-ilz")
                    : (info.isNarrow ? "fc-forma-aCI" : "fc-forma-Nca"), info.hasNavLink
                    ? joinClassNames(primaryPressableClass, outlineOffsetClass)
                    : primaryClass)
                : joinClassNames("fc-forma-Skl", info.isNarrow ? "fc-forma-aCI" : "fc-forma-Nca", info.hasNavLink && mutedHoverPressableClass), info.monthText && "fc-forma-DIS"),
            dayCellInnerClass: (info) => joinClassNames(info.inPopover && "fc-forma-3N5"),
            /* Popover
            --------------------------------------------------------------------------------------------- */
            popoverFormat: { day: "numeric", weekday: "long" },
            popoverClass: "fc-forma-wsy fc-forma-tfB fc-forma-RJG fc-forma-b7K fc-forma-tkw fc-forma-aNc fc-forma-n5m",
            popoverCloseClass: `fc-forma-bCs fc-forma-1EY fc-forma-ZnE fc-forma-SyR fc-forma-iS4 fc-forma-Fvv ${mutedHoverPressableClass} ${outlineWidthFocusClass} ${primaryOutlineColorClass} fc-forma-Z9U`,
            popoverCloseContent: () => dismiss(`fc-forma-XUJ ${mutedFgPressableGroupClass}`),
            /* Lane
            --------------------------------------------------------------------------------------------- */
            dayLaneClass: (info) => joinClassNames("fc-forma-wsy", info.isMajor ? "fc-forma-BST" : "fc-forma-tfB", info.isDisabled && "fc-forma-zNL"),
            dayLaneInnerClass: (info) => (info.isStack
                ? "fc-forma-gMS"
                : info.isNarrow ? "fc-forma-148" : "fc-forma-Jzj fc-forma-B3G"),
            slotLaneClass: getSlotClass,
            /* List Day
            --------------------------------------------------------------------------------------------- */
            listDayClass: (info) => joinClassNames(!info.isLast && "fc-forma-zi1 fc-forma-tfB", "fc-forma-dl1 fc-forma-1sP fc-forma-EF4"),
            listDayHeaderClass: (info) => joinClassNames("fc-forma-yi0 fc-forma-vVE fc-forma-aHX fc-forma-IJJ fc-forma-dl1 fc-forma-sgX fc-forma-EF4", info.isToday && "fc-forma-iSi fc-forma-Baf"),
            listDayHeaderInnerClass: (info) => joinClassNames("fc-forma-cJ3", !info.level
                ? joinClassNames("fc-forma-9ZS", info.isToday && "fc-forma-DIS")
                : "fc-forma-a3B", info.hasNavLink && "fc-forma-Eu0"),
            listDayBodyClass: "fc-forma-1El fc-forma-2KU fc-forma-lqx fc-forma-Pms",
            /* Single Month (in Multi-Month)
            --------------------------------------------------------------------------------------------- */
            singleMonthClass: (info) => joinClassNames(info.multiMonthColumns > 1 && "fc-forma-jD5", (info.multiMonthColumns === 1 && !info.isLast) && "fc-forma-tfB fc-forma-zi1"),
            singleMonthHeaderClass: (info) => joinClassNames(info.multiMonthColumns > 1
                ? "fc-forma-cM0"
                : "fc-forma-dl6 fc-forma-zi1 fc-forma-tfB fc-forma-RJG", "fc-forma-XpK"),
            singleMonthHeaderInnerClass: (info) => joinClassNames("fc-forma-aCI fc-forma-Fvv fc-forma-DIS", info.hasNavLink && mutedHoverPressableClass, info.isNarrow ? "fc-forma-1Po" : "fc-forma-9ZS"),
            /* Misc Table
            --------------------------------------------------------------------------------------------- */
            tableHeaderClass: "fc-forma-RJG",
            fillerClass: "fc-forma-wsy fc-forma-tfB fc-forma-lMo",
            dayNarrowWidth: 100,
            dayHeaderRowClass: "fc-forma-wsy fc-forma-tfB",
            dayRowClass: "fc-forma-wsy fc-forma-tfB",
            slotHeaderRowClass: "fc-forma-wsy fc-forma-tfB",
            slotHeaderClass: getSlotClass,
            /* Misc Content
            --------------------------------------------------------------------------------------------- */
            navLinkClass: `${outlineWidthFocusClass} ${primaryOutlineColorClass}`,
            inlineWeekNumberClass: (info) => joinClassNames("fc-forma-1EY fc-forma-iD1 fc-forma-TZ4 fc-forma-kmj", info.isNarrow
                ? `fc-forma-2ik fc-forma-SEP fc-forma-KUX ${xxsTextClass}`
                : "fc-forma-ZnE fc-forma-iS4 fc-forma-a3B", info.hasNavLink
                ? mutedPressableClass
                : "fc-forma-Wv4"),
            nonBusinessHoursClass: "fc-forma-zNL",
            highlightClass: "fc-forma-rRL",
            nowIndicatorLineClass: "fc-forma-CH7 fc-forma-qQW fc-forma-Baf",
            nowIndicatorDotClass: "fc-forma-aAW fc-forma-Vpk fc-forma-Baf fc-forma-63n fc-forma-AAA fc-forma-GBJ fc-forma-c3P",
            /* Resource Day Header
            --------------------------------------------------------------------------------------------- */
            resourceDayHeaderClass: (info) => joinClassNames("fc-forma-wsy", info.isMajor ? "fc-forma-BST" : "fc-forma-tfB"),
            resourceDayHeaderInnerClass: (info) => joinClassNames("fc-forma-bvX fc-forma-dl1 fc-forma-sgX", info.isNarrow ? "fc-forma-a3B" : "fc-forma-9yp"),
            /* Resource Data Grid
            --------------------------------------------------------------------------------------------- */
            resourceColumnHeaderClass: "fc-forma-wsy fc-forma-tfB fc-forma-E9P",
            resourceColumnHeaderInnerClass: "fc-forma-bvX fc-forma-9yp",
            resourceColumnResizerClass: "fc-forma-1EY fc-forma-AWB fc-forma-4Tv fc-forma-dnf",
            resourceGroupHeaderClass: "fc-forma-wsy fc-forma-tfB fc-forma-Wv4",
            resourceGroupHeaderInnerClass: "fc-forma-bvX fc-forma-9yp",
            resourceCellClass: "fc-forma-wsy fc-forma-tfB",
            resourceCellInnerClass: "fc-forma-bvX fc-forma-9yp",
            resourceIndentClass: "fc-forma-Wga fc-forma-p9t fc-forma-E9P",
            resourceExpanderClass: `fc-forma-bCs fc-forma-KUX fc-forma-Fvv ${mutedHoverPressableClass} ${outlineWidthFocusClass} ${primaryOutlineColorClass}`,
            resourceExpanderContent: (info) => chevronDown(joinClassNames(`fc-forma-vnf ${mutedFgPressableGroupClass}`, !info.isExpanded && "fc-forma-KxI fc-forma-ZW3")),
            resourceHeaderRowClass: "fc-forma-wsy fc-forma-tfB",
            resourceRowClass: "fc-forma-wsy fc-forma-tfB",
            resourceColumnDividerClass: "fc-forma-1Wx fc-forma-tfB fc-forma-a7i fc-forma-Wv4",
            /* Timeline Lane
            --------------------------------------------------------------------------------------------- */
            resourceGroupLaneClass: "fc-forma-wsy fc-forma-tfB fc-forma-Wv4",
            resourceLaneClass: "fc-forma-wsy fc-forma-tfB",
            resourceLaneBottomClass: (info) => info.options.eventOverlap && "fc-forma-vYi",
            timelineBottomClass: "fc-forma-vYi",
        },
        views: {
            dayGrid: {
                ...dayRowCommonClasses,
                dayHeaderDividerClass: "fc-forma-zi1 fc-forma-tfB",
                dayCellBottomClass: getShortDayCellBottomClass,
                backgroundEventInnerClass: "fc-forma-dl1 fc-forma-1sP fc-forma-LMv",
            },
            dayGridMonth: {
                dayHeaderFormat: { weekday: "long" },
            },
            multiMonth: {
                ...dayRowCommonClasses,
                dayHeaderDividerClass: (info) => joinClassNames(info.multiMonthColumns === 1 && "fc-forma-zi1 fc-forma-tfB"),
                dayCellBottomClass: getShortDayCellBottomClass,
                dayHeaderInnerClass: (info) => info.isNarrow && "fc-forma-V1v",
                tableBodyClass: (info) => joinClassNames(info.multiMonthColumns > 1 && "fc-forma-wsy fc-forma-tfB fc-forma-Fvv fc-forma-pKG"),
            },
            timeGrid: {
                ...dayRowCommonClasses,
                dayHeaderDividerClass: "fc-forma-zi1 fc-forma-tfB",
                dayCellBottomClass: tallDayCellBottomClass,
                dayHeaderAlign: "start",
                /* TimeGrid > Week Number Header
                ------------------------------------------------------------------------------------------- */
                weekNumberHeaderClass: "fc-forma-RNn fc-forma-LMv",
                weekNumberHeaderInnerClass: (info) => joinClassNames("fc-forma-gMS fc-forma-iS4 fc-forma-Fvv fc-forma-a3B", info.hasNavLink && mutedHoverPressableClass),
                /* TimeGrid > All-Day Header
                ------------------------------------------------------------------------------------------- */
                allDayHeaderClass: "fc-forma-XpK fc-forma-LMv",
                allDayHeaderInnerClass: (info) => joinClassNames("fc-forma-bvX fc-forma-2HE", info.isNarrow ? xxsTextClass : "fc-forma-a3B"),
                allDayDividerClass: "fc-forma-zi1 fc-forma-tfB",
                /* TimeGrid > Slot Header
                ------------------------------------------------------------------------------------------- */
                slotHeaderClass: "fc-forma-LMv",
                slotHeaderInnerClass: (info) => joinClassNames("fc-forma-bvX", info.isNarrow ? xxsTextClass : "fc-forma-a3B"),
                slotHeaderDividerClass: "fc-forma-USt fc-forma-tfB",
            },
            list: {
                /* List-View > List-Item Event
                ------------------------------------------------------------------------------------------- */
                listItemEventClass: (info) => joinClassNames("fc-forma-bCs fc-forma-riO fc-forma-lNM fc-forma-IJJ fc-forma-Fvv", info.isInteractive
                    ? eventFaintPressableClass
                    : eventFaintBgClass),
                listItemEventInnerClass: "fc-forma-tgZ fc-forma-9yp",
                listItemEventTimeClass: "fc-forma-yi0 fc-forma-roZ fc-forma-aHX fc-forma-TZ4 fc-forma-pKG fc-forma-IPx",
                listItemEventTitleClass: (info) => joinClassNames("fc-forma-1El fc-forma-2KU fc-forma-TZ4 fc-forma-pKG fc-forma-C8a", info.event.url && "fc-forma-Ogp"),
                /* No-Events Screen
                ------------------------------------------------------------------------------------------- */
                noEventsClass: "fc-forma-1El fc-forma-dl1 fc-forma-sgX fc-forma-XpK fc-forma-E9P",
                noEventsInnerClass: "fc-forma-P9h",
            },
            timeline: {
                /* Timeline > Row Event
                ------------------------------------------------------------------------------------------- */
                rowEventClass: (info) => info.isEnd && "fc-forma-9hC",
                rowEventInnerClass: (info) => (info.options.eventOverlap
                    ? "fc-forma-s0x"
                    : "fc-forma-dl6"),
                /* Timeline > More-Link
                ------------------------------------------------------------------------------------------- */
                rowMoreLinkClass: `fc-forma-9hC fc-forma-Ika fc-forma-Fvv fc-forma-wsy fc-forma-d0j fc-forma-4MR ${strongSolidPressableClass} fc-forma-vwH`,
                rowMoreLinkInnerClass: "fc-forma-aCI fc-forma-s0x fc-forma-a3B",
                /* Timeline > Slot Header
                ------------------------------------------------------------------------------------------- */
                slotHeaderAlign: (info) => info.isTime ? "start" : "center",
                slotHeaderClass: (info) => joinClassNames("fc-forma-E9P", !info.level && "fc-forma-pKG"),
                slotHeaderInnerClass: (info) => joinClassNames("fc-forma-bvX fc-forma-9yp", info.hasNavLink && "fc-forma-Eu0"),
                slotHeaderDividerClass: "fc-forma-zi1 fc-forma-tfB",
            },
        },
    };
    /* SVGs
    ------------------------------------------------------------------------------------------------- */
    function chevronDown(className) {
        return u("svg", { className: className, xmlns: "http://www.w3.org/2000/svg", width: "20", height: "20", viewBox: "0 0 20 20", fill: "currentColor", children: u("path", { d: "M15.8527 7.64582C16.0484 7.84073 16.0489 8.15731 15.854 8.35292L10.389 13.8374C10.1741 14.0531 9.82477 14.0531 9.60982 13.8374L4.14484 8.35292C3.94993 8.15731 3.95049 7.84073 4.1461 7.64582C4.34171 7.4509 4.65829 7.45147 4.85321 7.64708L9.99942 12.8117L15.1456 7.64708C15.3406 7.45147 15.6571 7.4509 15.8527 7.64582Z" }) });
    }
    function chevronDoubleLeft(className) {
        return u("svg", { className: className, xmlns: "http://www.w3.org/2000/svg", width: "20", height: "20", viewBox: "0 0 20 20", fill: "currentColor", children: u("path", { d: "M11.3544 15.8527C11.1594 16.0484 10.8429 16.0489 10.6472 15.854L5.16276 10.389C4.94705 10.1741 4.94705 9.82477 5.16276 9.60982L10.6472 4.14484C10.8429 3.94993 11.1594 3.95049 11.3544 4.1461C11.5493 4.34171 11.5487 4.65829 11.3531 4.85321L6.18851 9.99942L11.3531 15.1456C11.5487 15.3406 11.5493 15.6571 11.3544 15.8527ZM15.3534 15.8527C15.1585 16.0484 14.8419 16.0489 14.6463 15.854L9.16178 10.389C8.94607 10.1741 8.94607 9.82477 9.16178 9.60982L14.6463 4.14484C14.8419 3.94993 15.1585 3.95049 15.3534 4.1461C15.5483 4.34171 15.5477 4.65829 15.3521 4.85321L10.1875 9.99942L15.3521 15.1456C15.5477 15.3406 15.5483 15.6571 15.3534 15.8527Z" }) });
    }
    function dismiss(className) {
        return u("svg", { className: className, xmlns: "http://www.w3.org/2000/svg", width: "20", height: "20", viewBox: "0 0 20 20", fill: "currentColor", children: u("path", { d: "M4.08859 4.21569L4.14645 4.14645C4.32001 3.97288 4.58944 3.9536 4.78431 4.08859L4.85355 4.14645L10 9.293L15.1464 4.14645C15.32 3.97288 15.5894 3.9536 15.7843 4.08859L15.8536 4.14645C16.0271 4.32001 16.0464 4.58944 15.9114 4.78431L15.8536 4.85355L10.707 10L15.8536 15.1464C16.0271 15.32 16.0464 15.5894 15.9114 15.7843L15.8536 15.8536C15.68 16.0271 15.4106 16.0464 15.2157 15.9114L15.1464 15.8536L10 10.707L4.85355 15.8536C4.67999 16.0271 4.41056 16.0464 4.21569 15.9114L4.14645 15.8536C3.97288 15.68 3.9536 15.4106 4.08859 15.2157L4.14645 15.1464L9.293 10L4.14645 4.85355C3.97288 4.67999 3.9536 4.41056 4.08859 4.21569L4.14645 4.14645L4.08859 4.21569Z" }) });
    }

    globalPlugins.push(index);

})(FullCalendar.Shared);
