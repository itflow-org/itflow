/*!
FullCalendar (Vanilla JS) v7.0.0
Docs & License: https://fullcalendar.io
(c) 2026 Adam Shaw
*/
(function ({ H: joinClassNames, u, S, G: globalPlugins }) {
    

    // usually 11px font / 12px line-height
    const xxsTextClass = "fc-pulse-vQz";
    // outline
    const outlineWidthClass = "fc-pulse-0Bj";
    const outlineWidthFocusClass = "fc-pulse-uqo";
    const outlineWidthGroupFocusClass = "fc-pulse-nPR";
    const outlineOffsetClass = "fc-pulse-3Xj";
    const outlineInsetClass = "fc-pulse-fFh";
    const tertiaryOutlineColorClass = "fc-pulse-8qP";
    const tertiaryOutlineFocusClass = `${outlineWidthFocusClass} ${tertiaryOutlineColorClass}`;
    // shadows
    const smallBoxShadowClass = "fc-pulse-XCS";
    const largeBoxShadowClass = "fc-pulse-KdO";
    // neutral buttons
    const strongSolidPressableClass = joinClassNames("fc-pulse-X4o", "fc-pulse-V7v", "fc-pulse-08e");
    const mutedHoverClass = "fc-pulse-OT5";
    const mutedHoverPressableClass = `${mutedHoverClass} fc-pulse-wm1 fc-pulse-rQT`;
    const faintHoverClass = "fc-pulse-Hc8";
    const faintHoverPressableClass = `${faintHoverClass} fc-pulse-oJW fc-pulse-G9W`;
    // controls
    const selectedButtonClass = `fc-pulse-V8J fc-pulse-fhL ${largeBoxShadowClass} ${tertiaryOutlineFocusClass}`;
    const unselectedButtonClass = `fc-pulse-qNi ${mutedHoverPressableClass} ${tertiaryOutlineFocusClass}`;
    // primary *toolbar button*
    const primaryClass = "fc-pulse-WBN fc-pulse-jv3";
    const primaryPressableClass = `${primaryClass} fc-pulse-S3M fc-pulse-jnQ`;
    const primaryButtonClass = `${primaryPressableClass} fc-pulse-d0j ${tertiaryOutlineFocusClass} ${outlineOffsetClass}`;
    // secondary *toolbar button*
    const secondaryPressableClass = "fc-pulse-EVd fc-pulse-JnV fc-pulse-bAi fc-pulse-s1M";
    const secondaryButtonClass = `${secondaryPressableClass} ${tertiaryOutlineFocusClass} fc-pulse-07j`;
    const secondaryButtonIconClass = "fc-pulse-XUJ fc-pulse-8tO fc-pulse-TLh fc-pulse-zfc";
    // tertiary
    const tertiaryClass = "fc-pulse-Lu4 fc-pulse-c79";
    const tertiaryPressableClass = `${tertiaryClass} fc-pulse-Q9K fc-pulse-dpx fc-pulse-Lto`;
    const tertiaryPressableGroupClass = `${tertiaryClass} fc-pulse-F4j fc-pulse-wy6 fc-pulse-Gjm`;
    // interactive neutral foregrounds
    const mutedFgPressableGroupClass = "fc-pulse-UnP fc-pulse-FdK fc-pulse-wBD";
    // transparent resizer for mouse
    const blockPointerResizerClass = "fc-pulse-1EY fc-pulse-pps fc-pulse-vs6";
    const rowPointerResizerClass = `${blockPointerResizerClass} fc-pulse-AWB fc-pulse-hza`;
    const columnPointerResizerClass = `${blockPointerResizerClass} fc-pulse-MaV fc-pulse-uuA`;
    // circle resizer for touch
    const blockTouchResizerClass = "fc-pulse-1EY fc-pulse-3wQ fc-pulse-wsy fc-pulse-lNM fc-pulse-aRD fc-pulse-AAA";
    const rowTouchResizerClass = `${blockTouchResizerClass} fc-pulse-ERR fc-pulse-Dq8`;
    const columnTouchResizerClass = `${blockTouchResizerClass} fc-pulse-1V6 fc-pulse-F99`;
    const tallDayCellBottomClass = "fc-pulse-mhE";
    const getShortDayCellBottomClass = (info) => joinClassNames(!info.isNarrow && "fc-pulse-84e");
    const dayRowCommonClasses = {
        /* Day Row > List-Item Event
        ----------------------------------------------------------------------------------------------- */
        listItemEventClass: (info) => joinClassNames("fc-pulse-Ika fc-pulse-7A6 fc-pulse-Fvv", info.isNarrow ? "fc-pulse-cKZ" : "fc-pulse-rVY", info.isSelected
            ? "fc-pulse-LKt"
            : info.isInteractive
                ? mutedHoverPressableClass
                : mutedHoverClass),
        listItemEventInnerClass: (info) => joinClassNames("fc-pulse-dl1 fc-pulse-1sP fc-pulse-XpK fc-pulse-N2M", info.isNarrow
            ? `fc-pulse-z5u ${xxsTextClass}`
            : "fc-pulse-2rx fc-pulse-a3B"),
        listItemEventTimeClass: (info) => joinClassNames(info.isNarrow ? "fc-pulse-F1o" : "fc-pulse-oQ2", "fc-pulse-NPw fc-pulse-TZ4 fc-pulse-pKG fc-pulse-1Zl"),
        listItemEventTitleClass: (info) => joinClassNames(info.isNarrow ? "fc-pulse-F1o" : "fc-pulse-oQ2", "fc-pulse-1OT fc-pulse-TZ4 fc-pulse-pKG fc-pulse-OLq", info.timeText && "fc-pulse-IPx"),
        /* Day Row > Row Event
        ----------------------------------------------------------------------------------------------- */
        rowEventClass: (info) => joinClassNames(info.isStart && (info.isNarrow ? "fc-pulse-Jzj" : "fc-pulse-Wga"), info.isEnd && (info.isNarrow ? "fc-pulse-3e1" : "fc-pulse-KYn")),
        rowEventInnerClass: (info) => info.isNarrow ? "fc-pulse-z5u" : "fc-pulse-2rx",
        /* Day Row > More-Link
        ----------------------------------------------------------------------------------------------- */
        rowMoreLinkClass: (info) => joinClassNames("fc-pulse-Ika fc-pulse-wsy fc-pulse-Fvv", info.isNarrow
            ? `fc-pulse-cKZ fc-pulse-xkd ${mutedHoverPressableClass}`
            : "fc-pulse-sI7 fc-pulse-rVY fc-pulse-d0j fc-pulse-LKt fc-pulse-8bo fc-pulse-QpP"),
        rowMoreLinkInnerClass: (info) => joinClassNames(info.isNarrow
            ? `fc-pulse-7A6 ${xxsTextClass}`
            : "fc-pulse-KUX fc-pulse-a3B", "fc-pulse-EB5"),
    };
    var index = {
        name: "theme-pulse",
        optionDefaults: {
            className: "fc-pulse-V08 fc-pulse-n5m",
            viewClass: (info) => {
                const hasBorderTop = info.options.headerToolbar || !info.borderlessTop;
                const hasBorderBottom = info.options.footerToolbar || !info.borderlessBottom;
                const hasBorderX = !info.borderlessX;
                return joinClassNames("fc-pulse-FMd", hasBorderTop && "fc-pulse-ku3", hasBorderBottom && "fc-pulse-zi1", hasBorderX && "fc-pulse-1Wx", (hasBorderTop && hasBorderX) && "fc-pulse-Z7Q", (hasBorderBottom && hasBorderX) && "fc-pulse-2qh", (hasBorderTop && hasBorderBottom && hasBorderX) && smallBoxShadowClass, !info.isHeightAuto && "fc-pulse-pKG");
            },
            /* Toolbar
            --------------------------------------------------------------------------------------------- */
            toolbarClass: (info) => joinClassNames("fc-pulse-dl1 fc-pulse-1sP fc-pulse-dNl fc-pulse-XpK fc-pulse-N2M fc-pulse-yth", info.borderlessX && "fc-pulse-Apf"),
            toolbarSectionClass: "fc-pulse-yi0 fc-pulse-dl1 fc-pulse-1sP fc-pulse-XpK fc-pulse-yth",
            toolbarTitleClass: "fc-pulse-AVD fc-pulse-DIS fc-pulse-EB5",
            buttonGroupClass: (info) => joinClassNames("fc-pulse-z5u fc-pulse-Fvv fc-pulse-dl1 fc-pulse-1sP fc-pulse-XpK", info.hasSelection
                ? "fc-pulse-ntj"
                : `fc-pulse-7tK ${smallBoxShadowClass}`),
            buttonClass: (info) => joinClassNames("fc-pulse-bCs fc-pulse-dl6 fc-pulse-dl1 fc-pulse-1sP fc-pulse-XpK fc-pulse-9yp fc-pulse-Z9U", info.isIconOnly ? "fc-pulse-Eaq" : "fc-pulse-KRz", info.buttonGroup?.hasSelection
                ? joinClassNames("fc-pulse-Fvv", info.isSelected
                    ? selectedButtonClass
                    : joinClassNames(unselectedButtonClass, "fc-pulse-kWT fc-pulse-JIC fc-pulse-d0j"))
                : joinClassNames("fc-pulse-wsy", info.buttonGroup
                    ? "fc-pulse-kWT fc-pulse-F5S fc-pulse-Bn0 fc-pulse-lFh"
                    : "fc-pulse-Fvv", info.isPrimary
                    ? joinClassNames(primaryButtonClass, !info.buttonGroup && largeBoxShadowClass)
                    : joinClassNames(secondaryButtonClass, "fc-pulse-dck", !info.buttonGroup
                        ? `fc-pulse-7tK ${smallBoxShadowClass}`
                        : "fc-pulse-WWn fc-pulse-7NL"))),
            buttons: {
                prev: {
                    iconContent: () => chevronDown(joinClassNames(secondaryButtonIconClass, "fc-pulse-z44 fc-pulse-keW"))
                },
                next: {
                    iconContent: () => chevronDown(joinClassNames(secondaryButtonIconClass, "fc-pulse-KxI fc-pulse-ZW3"))
                },
                prevYear: {
                    iconContent: () => chevronsLeft(joinClassNames(secondaryButtonIconClass, "fc-pulse-asP"))
                },
                nextYear: {
                    iconContent: () => chevronsLeft(joinClassNames(secondaryButtonIconClass, "fc-pulse-jmT fc-pulse-jY6"))
                },
            },
            /* Abstract Event
            --------------------------------------------------------------------------------------------- */
            eventShortHeight: 50,
            eventColor: "var(--fc-pulse-event)",
            eventContrastColor: "var(--fc-pulse-event-contrast)",
            eventClass: (info) => joinClassNames(info.isDragging && "fc-pulse-n5m", info.event.url && "fc-pulse-JiE", info.isSelected
                ? joinClassNames(outlineWidthClass, info.isDragging && "fc-pulse-1kP")
                : outlineWidthFocusClass, tertiaryOutlineColorClass),
            /* Background Event
            --------------------------------------------------------------------------------------------- */
            backgroundEventColor: "var(--fc-pulse-background-event)",
            backgroundEventClass: "fc-pulse-gTC fc-pulse-jsy fc-pulse-DO7",
            backgroundEventTitleClass: (info) => joinClassNames("fc-pulse-lMo fc-pulse-L1Y", (info.isNarrow || info.isShort)
                ? `fc-pulse-iS4 ${xxsTextClass}`
                : "fc-pulse-3N5 fc-pulse-a3B", "fc-pulse-EB5"),
            /* List-Item Event
            --------------------------------------------------------------------------------------------- */
            listItemEventTitleClass: "fc-pulse-EB5",
            listItemEventTimeClass: "fc-pulse-UnP",
            /* Block Event
            --------------------------------------------------------------------------------------------- */
            blockEventClass: (info) => joinClassNames("fc-pulse-bCs fc-pulse-eYX fc-pulse-d0j fc-pulse-DO7 fc-pulse-YjJ fc-pulse-vwH fc-pulse-Frw", info.isInteractive && "fc-pulse-st8", (info.isDragging && !info.isSelected) && "fc-pulse-iTG"),
            blockEventInnerClass: "fc-pulse-i9F fc-pulse-cfp",
            blockEventTimeClass: "fc-pulse-TZ4 fc-pulse-pKG fc-pulse-1Zl",
            blockEventTitleClass: "fc-pulse-TZ4 fc-pulse-pKG fc-pulse-OLq",
            /* Row Event
            --------------------------------------------------------------------------------------------- */
            rowEventClass: (info) => joinClassNames("fc-pulse-Ika fc-pulse-JIC", info.isStart && "fc-pulse-kmj fc-pulse-3J4", info.isEnd && "fc-pulse-Skl fc-pulse-USt"),
            rowEventBeforeClass: (info) => joinClassNames(info.isStartResizable && joinClassNames(info.isSelected ? rowTouchResizerClass : rowPointerResizerClass, "fc-pulse-11a")),
            rowEventAfterClass: (info) => joinClassNames(info.isEndResizable && joinClassNames(info.isSelected ? rowTouchResizerClass : rowPointerResizerClass, "fc-pulse-Tuc")),
            rowEventInnerClass: (info) => joinClassNames("fc-pulse-dl1 fc-pulse-1sP fc-pulse-XpK", info.isNarrow ? xxsTextClass : "fc-pulse-a3B"),
            rowEventTimeClass: (info) => (info.isNarrow ? "fc-pulse-a7i" : "fc-pulse-C2j"),
            rowEventTitleClass: (info) => joinClassNames(info.isNarrow ? "fc-pulse-oQ2" : "fc-pulse-aCI", "fc-pulse-1OT"),
            /* Column Event
            --------------------------------------------------------------------------------------------- */
            columnEventClass: (info) => joinClassNames("fc-pulse-1Wx fc-pulse-A3h fc-pulse-Y7n", info.isStart && joinClassNames("fc-pulse-ku3 fc-pulse-wko", info.isNarrow ? "fc-pulse-sEX" : "fc-pulse-6iV"), info.isEnd && joinClassNames("fc-pulse-zi1 fc-pulse-L2o", info.isNarrow ? "fc-pulse-Ika" : "fc-pulse-WdH")),
            columnEventBeforeClass: (info) => joinClassNames(info.isStartResizable && joinClassNames(info.isSelected ? columnTouchResizerClass : columnPointerResizerClass, "fc-pulse-YDC")),
            columnEventAfterClass: (info) => joinClassNames(info.isEndResizable && joinClassNames(info.isSelected ? columnTouchResizerClass : columnPointerResizerClass, "fc-pulse-fJL")),
            columnEventInnerClass: (info) => joinClassNames("fc-pulse-dl1", info.isShort
                ? "fc-pulse-1sP fc-pulse-XpK fc-pulse-NWN fc-pulse-iS4"
                : joinClassNames("fc-pulse-sgX", info.isNarrow ? "fc-pulse-aCI fc-pulse-2rx" : "fc-pulse-Nca fc-pulse-Jhn"), (info.isNarrow || info.isShort) ? xxsTextClass : "fc-pulse-a3B"),
            columnEventTimeClass: (info) => joinClassNames(!info.isShort && (info.isNarrow ? "fc-pulse-166" : "fc-pulse-4dx")),
            columnEventTitleClass: (info) => joinClassNames(!info.isShort && (info.isNarrow ? "fc-pulse-2rx" : "fc-pulse-Jhn"), "fc-pulse-1OT"),
            /* More-Link
            --------------------------------------------------------------------------------------------- */
            moreLinkClass: `${outlineWidthFocusClass} ${tertiaryOutlineColorClass}`,
            moreLinkInnerClass: "fc-pulse-TZ4 fc-pulse-pKG",
            columnMoreLinkClass: `fc-pulse-cJ3 fc-pulse-wsy fc-pulse-d0j fc-pulse-4MR fc-pulse-KzJ ${strongSolidPressableClass} fc-pulse-vwH fc-pulse-A3h fc-pulse-Y7n`,
            columnMoreLinkInnerClass: (info) => joinClassNames(info.isNarrow
                ? `fc-pulse-KUX ${xxsTextClass}`
                : "fc-pulse-iS4 fc-pulse-a3B", "fc-pulse-EB5"),
            /* Day Header
            --------------------------------------------------------------------------------------------- */
            dayHeaderClass: (info) => joinClassNames("fc-pulse-E9P", info.inPopover ? "fc-pulse-zi1 fc-pulse-FMd fc-pulse-Kwh" :
                info.isMajor && "fc-pulse-wsy fc-pulse-dck"),
            dayHeaderInnerClass: (info) => joinClassNames("fc-pulse-dl1 fc-pulse-1sP fc-pulse-XpK", info.isNarrow ? "fc-pulse-a3B" : "fc-pulse-9yp", info.inPopover ? joinClassNames("fc-pulse-cGD fc-pulse-aCI fc-pulse-2rx fc-pulse-Fvv fc-pulse-C8a", "fc-pulse-EB5", info.hasNavLink && mutedHoverPressableClass) : !info.dayNumberText ? joinClassNames("fc-pulse-cKZ fc-pulse-ckd fc-pulse-2rx fc-pulse-ZrE fc-pulse-Fvv", "fc-pulse-UnP", info.hasNavLink && mutedHoverPressableClass) : !info.isToday ? joinClassNames("fc-pulse-fn8 fc-pulse-60W fc-pulse-TFV fc-pulse-ZrE fc-pulse-Fvv", "fc-pulse-UnP", info.hasNavLink && mutedHoverPressableClass) : ("fc-pulse-bCs fc-pulse-fn8 fc-pulse-2tF fc-pulse-lIh fc-pulse-hS8")),
            dayHeaderContent: (info) => ((info.inPopover || !info.dayNumberText || !info.isToday) ? (u(S, { children: info.text })) : (u(S, { children: info.textParts.map((textPart, i) => (u("span", { className: joinClassNames("fc-pulse-jm6", (textPart.type === "day" && info.isToday)
                        ? joinClassNames("fc-pulse-YPX fc-pulse-mTY fc-pulse-IY5 fc-pulse-AAA fc-pulse-C8a", "fc-pulse-dl1 fc-pulse-1sP fc-pulse-XpK fc-pulse-E9P", info.hasNavLink
                            ? `${tertiaryPressableGroupClass} ${outlineWidthGroupFocusClass} ${outlineOffsetClass} ${tertiaryOutlineColorClass}`
                            : tertiaryClass)
                        : "fc-pulse-UnP"), children: textPart.value }, i))) }))),
            /* Day Cell
            --------------------------------------------------------------------------------------------- */
            dayCellClass: (info) => joinClassNames("fc-pulse-wsy", info.isMajor
                ? "fc-pulse-dck"
                : "fc-pulse-FMd"),
            dayCellTopClass: (info) => joinClassNames(info.isNarrow ? "fc-pulse-84e" : "fc-pulse-p7s", "fc-pulse-dl1 fc-pulse-1sP fc-pulse-LMv"),
            dayCellTopInnerClass: (info) => joinClassNames("fc-pulse-dl1 fc-pulse-1sP fc-pulse-XpK", info.isNarrow
                ? `fc-pulse-SEP fc-pulse-oM6 ${xxsTextClass}`
                : "fc-pulse-V9v fc-pulse-TFV fc-pulse-9yp", !info.isToday
                ? joinClassNames("fc-pulse-kmj fc-pulse-TZ4", !info.isOther && "fc-pulse-C8a", info.isNarrow ? "fc-pulse-aCI" : "fc-pulse-Nca", info.monthText ? "fc-pulse-EB5" : "fc-pulse-UnP", info.hasNavLink && mutedHoverPressableClass)
                : joinClassNames("fc-pulse-bCs fc-pulse-hS8", info.isNarrow
                    ? "fc-pulse-148"
                    : "fc-pulse-fn8")),
            dayCellTopContent: (info) => (!info.isToday ? (u(S, { children: info.text })) : (u(S, { children: info.textParts.map((textPart, i) => (u("span", { className: joinClassNames("fc-pulse-jm6", (textPart.type === "day" && info.isToday)
                        ? joinClassNames("fc-pulse-AAA fc-pulse-C8a", "fc-pulse-dl1 fc-pulse-1sP fc-pulse-XpK fc-pulse-E9P", info.isNarrow
                            ? "fc-pulse-XUJ"
                            : "fc-pulse-MSG fc-pulse-YPX fc-pulse-mTY", info.hasNavLink
                            ? `${tertiaryPressableGroupClass} ${outlineWidthGroupFocusClass} ${outlineOffsetClass} ${tertiaryOutlineColorClass}`
                            : tertiaryClass)
                        : (info.monthText ? "fc-pulse-EB5" : "fc-pulse-UnP")), children: textPart.value }, i))) }))),
            dayCellInnerClass: (info) => joinClassNames(info.inPopover && "fc-pulse-3N5"),
            /* Popover
            --------------------------------------------------------------------------------------------- */
            popoverClass: "fc-pulse-aRD fc-pulse-wsy fc-pulse-dck fc-pulse-Fvv fc-pulse-pKG fc-pulse-tkw fc-pulse-gMS fc-pulse-aNc fc-pulse-n5m",
            popoverCloseClass: `fc-pulse-bCs fc-pulse-1EY fc-pulse-1b8 fc-pulse-Z42 fc-pulse-KUX fc-pulse-Fvv ${mutedHoverPressableClass} ${outlineWidthFocusClass} ${tertiaryOutlineColorClass} fc-pulse-Z9U`,
            popoverCloseContent: () => x(`fc-pulse-XUJ ${mutedFgPressableGroupClass}`),
            /* Lane
            --------------------------------------------------------------------------------------------- */
            dayLaneClass: (info) => joinClassNames("fc-pulse-wsy", info.isMajor ? "fc-pulse-dck" : "fc-pulse-FMd", info.isDisabled && "fc-pulse-Kwh"),
            dayLaneInnerClass: (info) => (info.isStack
                ? "fc-pulse-gMS"
                : info.isNarrow ? "fc-pulse-148" : "fc-pulse-cKZ"),
            slotLaneClass: (info) => joinClassNames("fc-pulse-wsy fc-pulse-FMd", info.isMinor && "fc-pulse-TN2"),
            /* List Day
            --------------------------------------------------------------------------------------------- */
            listDayClass: (info) => joinClassNames("fc-pulse-dl1 fc-pulse-sgX", !info.isLast && "fc-pulse-zi1 fc-pulse-FMd"),
            listDayHeaderClass: "fc-pulse-nHS fc-pulse-zi1 fc-pulse-FMd fc-pulse-fpi fc-pulse-EB5 fc-pulse-dl1 fc-pulse-1sP fc-pulse-XpK fc-pulse-N2M",
            listDayHeaderInnerClass: (info) => joinClassNames("fc-pulse-cGD fc-pulse-ZrE fc-pulse-2rx fc-pulse-Fvv fc-pulse-9yp", !info.level && "fc-pulse-C8a", (!info.level && info.isToday)
                ? info.hasNavLink
                    ? joinClassNames(tertiaryPressableClass, outlineOffsetClass)
                    : tertiaryClass
                : info.hasNavLink && mutedHoverPressableClass),
            listDayBodyClass: "fc-pulse-sEX fc-pulse-ZrE fc-pulse-dl6 fc-pulse-tgZ",
            /* Single Month (in Multi-Month)
            --------------------------------------------------------------------------------------------- */
            singleMonthClass: (info) => joinClassNames(info.multiMonthColumns > 1 && "fc-pulse-rUb", (info.multiMonthColumns === 1 && !info.isLast) && "fc-pulse-zi1 fc-pulse-FMd"),
            singleMonthHeaderClass: (info) => joinClassNames(info.multiMonthColumns > 1
                ? "fc-pulse-lTO"
                : "fc-pulse-Jhn fc-pulse-zi1 fc-pulse-FMd fc-pulse-aRD", "fc-pulse-XpK"),
            singleMonthHeaderInnerClass: (info) => joinClassNames("fc-pulse-ZrE fc-pulse-2rx fc-pulse-Fvv fc-pulse-1Po fc-pulse-EB5 fc-pulse-C8a", info.hasNavLink && mutedHoverPressableClass),
            /* Misc Table
            --------------------------------------------------------------------------------------------- */
            tableBodyClass: "fc-pulse-aRD",
            fillerClass: "fc-pulse-wsy fc-pulse-FMd fc-pulse-lMo",
            dayNarrowWidth: 100,
            dayHeaderRowClass: "fc-pulse-wsy fc-pulse-FMd",
            dayRowClass: "fc-pulse-wsy fc-pulse-FMd",
            slotHeaderRowClass: "fc-pulse-wsy fc-pulse-FMd",
            slotHeaderInnerClass: "fc-pulse-UnP",
            /* Misc Content
            --------------------------------------------------------------------------------------------- */
            navLinkClass: `${outlineWidthFocusClass} ${tertiaryOutlineColorClass}`,
            inlineWeekNumberClass: (info) => joinClassNames("fc-pulse-1EY fc-pulse-rbS fc-pulse-TZ4 fc-pulse-Skl fc-pulse-UnP", info.isNarrow
                ? `fc-pulse-2ik fc-pulse-SEP fc-pulse-KUX ${xxsTextClass}`
                : "fc-pulse-ZnE fc-pulse-iS4 fc-pulse-a3B", info.hasNavLink && mutedHoverPressableClass),
            highlightClass: "fc-pulse-afe",
            nonBusinessHoursClass: "fc-pulse-Kwh",
            nowIndicatorLineClass: "fc-pulse-CH7 fc-pulse-qQW fc-pulse-upi",
            nowIndicatorDotClass: "fc-pulse-aAW fc-pulse-Vpk fc-pulse-upi fc-pulse-63n fc-pulse-AAA fc-pulse-GBJ fc-pulse-Y7n",
            /* Resource Day Header
            --------------------------------------------------------------------------------------------- */
            resourceDayHeaderAlign: "center",
            resourceDayHeaderClass: (info) => joinClassNames(info.isMajor && "fc-pulse-wsy fc-pulse-dck"),
            resourceDayHeaderInnerClass: (info) => joinClassNames("fc-pulse-bvX fc-pulse-dl1 fc-pulse-1sP fc-pulse-XpK fc-pulse-UnP", info.isNarrow ? "fc-pulse-a3B" : "fc-pulse-9yp"),
            /* Resource Data Grid
            --------------------------------------------------------------------------------------------- */
            resourceColumnHeaderClass: "fc-pulse-wsy fc-pulse-FMd fc-pulse-E9P",
            resourceColumnHeaderInnerClass: "fc-pulse-bvX fc-pulse-EB5 fc-pulse-9yp",
            resourceColumnResizerClass: "fc-pulse-1EY fc-pulse-AWB fc-pulse-4Tv fc-pulse-dnf",
            resourceGroupHeaderClass: "fc-pulse-wsy fc-pulse-FMd fc-pulse-LKt",
            resourceGroupHeaderInnerClass: "fc-pulse-bvX fc-pulse-EB5 fc-pulse-9yp",
            resourceCellClass: "fc-pulse-wsy fc-pulse-FMd",
            resourceCellInnerClass: "fc-pulse-bvX fc-pulse-EB5 fc-pulse-9yp",
            resourceIndentClass: "fc-pulse-Wga fc-pulse-p9t fc-pulse-E9P",
            resourceExpanderClass: `fc-pulse-bCs fc-pulse-KUX fc-pulse-Fvv ${mutedHoverPressableClass} ${outlineWidthFocusClass} ${tertiaryOutlineColorClass}`,
            resourceExpanderContent: (info) => chevronDown(joinClassNames(`fc-pulse-XUJ ${mutedFgPressableGroupClass}`, !info.isExpanded && "fc-pulse-KxI fc-pulse-ZW3")),
            resourceHeaderRowClass: "fc-pulse-wsy fc-pulse-FMd",
            resourceRowClass: "fc-pulse-wsy fc-pulse-FMd",
            resourceColumnDividerClass: "fc-pulse-USt fc-pulse-dck",
            /* Timeline Lane
            --------------------------------------------------------------------------------------------- */
            resourceGroupLaneClass: "fc-pulse-wsy fc-pulse-FMd fc-pulse-LKt",
            resourceLaneClass: "fc-pulse-wsy fc-pulse-FMd",
            resourceLaneBottomClass: (info) => joinClassNames(info.options.eventOverlap && "fc-pulse-uuA"),
            timelineBottomClass: "fc-pulse-uuA",
        },
        views: {
            dayGrid: {
                ...dayRowCommonClasses,
                tableHeaderClass: "fc-pulse-aRD",
                dayHeaderAlign: (info) => info.inPopover ? "start" : info.isNarrow ? "center" : "end",
                dayHeaderDividerClass: "fc-pulse-zi1 fc-pulse-FMd",
                dayCellBottomClass: getShortDayCellBottomClass,
            },
            multiMonth: {
                ...dayRowCommonClasses,
                viewClass: "fc-pulse-Kwh",
                tableHeaderClass: (info) => joinClassNames(info.multiMonthColumns === 1 && "fc-pulse-aRD"),
                tableBodyClass: (info) => joinClassNames(info.multiMonthColumns > 1 && "fc-pulse-wsy fc-pulse-FMd fc-pulse-Fvv fc-pulse-pKG"),
                dayHeaderAlign: (info) => info.inPopover ? "start" : info.isNarrow ? "center" : "end",
                dayHeaderDividerClass: (info) => joinClassNames(info.multiMonthColumns === 1 && "fc-pulse-zi1 fc-pulse-FMd"),
                dayCellBottomClass: getShortDayCellBottomClass,
            },
            timeGrid: {
                ...dayRowCommonClasses,
                tableHeaderClass: "fc-pulse-aRD",
                dayHeaderAlign: (info) => info.inPopover ? "start" : "center",
                dayHeaderDividerClass: (info) => joinClassNames("fc-pulse-zi1", info.options.allDaySlot
                    ? "fc-pulse-FMd"
                    : "fc-pulse-dck fc-pulse-rf6"),
                dayCellBottomClass: tallDayCellBottomClass,
                /* TimeGrid > Week Number Header
                ------------------------------------------------------------------------------------------- */
                weekNumberHeaderClass: "fc-pulse-XpK fc-pulse-LMv",
                weekNumberHeaderInnerClass: (info) => joinClassNames("fc-pulse-cKZ fc-pulse-TFV fc-pulse-ZrE fc-pulse-UnP fc-pulse-dl1 fc-pulse-1sP fc-pulse-XpK fc-pulse-Fvv", info.isNarrow ? "fc-pulse-a3B" : "fc-pulse-9yp", info.hasNavLink && mutedHoverPressableClass),
                /* TimeGrid > All-Day Header
                ------------------------------------------------------------------------------------------- */
                allDayHeaderClass: "fc-pulse-XpK",
                allDayHeaderInnerClass: (info) => joinClassNames("fc-pulse-bvX fc-pulse-UnP", info.isNarrow ? xxsTextClass : "fc-pulse-a3B"),
                allDayDividerClass: "fc-pulse-zi1 fc-pulse-dck fc-pulse-rf6",
                /* TimeGrid > Slot Header
                ------------------------------------------------------------------------------------------- */
                slotHeaderClass: "fc-pulse-LMv",
                slotHeaderInnerClass: (info) => joinClassNames("fc-pulse-eYX fc-pulse-bvX", info.isNarrow
                    ? `fc-pulse-Cy2 ${xxsTextClass}`
                    : "fc-pulse-uqG fc-pulse-a3B", info.isFirst && "fc-pulse-pps"),
                slotHeaderDividerClass: "fc-pulse-USt fc-pulse-FMd",
            },
            list: {
                viewClass: "fc-pulse-aRD",
                /* List-View > List-Item Event
                ------------------------------------------------------------------------------------------- */
                listItemEventClass: (info) => joinClassNames("fc-pulse-bCs fc-pulse-Jhn fc-pulse-Fvv", info.isInteractive
                    ? joinClassNames(faintHoverPressableClass, outlineInsetClass)
                    : faintHoverClass),
                listItemEventBeforeClass: "fc-pulse-5kF fc-pulse-YjJ fc-pulse-AAA",
                listItemEventInnerClass: "fc-pulse-eF2",
                listItemEventTimeClass: "fc-pulse-88I fc-pulse-yi0 fc-pulse-roZ fc-pulse-Cdk fc-pulse-KRz fc-pulse-dl6 fc-pulse-TZ4 fc-pulse-pKG fc-pulse-IPx fc-pulse-9yp",
                listItemEventTitleClass: (info) => joinClassNames("fc-pulse-1El fc-pulse-2KU fc-pulse-KRz fc-pulse-dl6 fc-pulse-TZ4 fc-pulse-pKG fc-pulse-9yp", info.event.url && "fc-pulse-Ogp"),
                /* No-Events Screen
                ------------------------------------------------------------------------------------------- */
                noEventsClass: "fc-pulse-1El fc-pulse-dl1 fc-pulse-sgX fc-pulse-XpK fc-pulse-E9P",
                noEventsInnerClass: "fc-pulse-P9h fc-pulse-UnP",
            },
            timeline: {
                tableHeaderClass: "fc-pulse-aRD",
                /* Timeline > Row Event
                ------------------------------------------------------------------------------------------- */
                rowEventClass: (info) => joinClassNames(info.isEnd && "fc-pulse-9hC"),
                rowEventInnerClass: (info) => info.options.eventOverlap ? "fc-pulse-Jhn" : "fc-pulse-dl6",
                /* Timeline > More-Link
                ------------------------------------------------------------------------------------------- */
                rowMoreLinkClass: `fc-pulse-9hC fc-pulse-Ika fc-pulse-wsy fc-pulse-d0j fc-pulse-4MR fc-pulse-Fvv ${strongSolidPressableClass} fc-pulse-vwH`,
                rowMoreLinkInnerClass: "fc-pulse-iS4 fc-pulse-EB5 fc-pulse-a3B",
                /* Timeline > Slot Header
                ------------------------------------------------------------------------------------------- */
                slotHeaderAlign: (info) => info.isTime ? "start" : "center",
                slotHeaderClass: (info) => joinClassNames(info.level > 0 && "fc-pulse-wsy fc-pulse-FMd", "fc-pulse-E9P"),
                slotHeaderInnerClass: (info) => joinClassNames("fc-pulse-bvX fc-pulse-9yp", info.isTime && joinClassNames("fc-pulse-eYX fc-pulse-4oC", info.isFirst && "fc-pulse-pps"), info.hasNavLink && "fc-pulse-Eu0"),
                slotHeaderDividerClass: "fc-pulse-zi1 fc-pulse-dck fc-pulse-qNs",
            },
        },
    };
    /* SVGs
    ------------------------------------------------------------------------------------------------- */
    function chevronDown(className) {
        return u("svg", { xmlns: "http://www.w3.org/2000/svg", className: className, width: "20", height: "20", viewBox: "0 0 24 24", fill: "none", stroke: "currentColor", strokeWidth: "2", strokeLinecap: "round", strokeLinejoin: "round", children: u("polyline", { points: "6 9 12 15 18 9" }) });
    }
    function chevronsLeft(className) {
        return u("svg", { xmlns: "http://www.w3.org/2000/svg", className: className, width: "20", height: "20", viewBox: "0 0 24 24", fill: "none", stroke: "currentColor", strokeWidth: "2", strokeLinecap: "round", strokeLinejoin: "round", children: [u("polyline", { points: "11 17 6 12 11 7" }), u("polyline", { points: "18 17 13 12 18 7" })] });
    }
    function x(className) {
        return u("svg", { xmlns: "http://www.w3.org/2000/svg", className: className, width: "20", height: "20", viewBox: "0 0 24 24", fill: "none", stroke: "currentColor", strokeWidth: "2", strokeLinecap: "round", strokeLinejoin: "round", children: [u("line", { x1: "18", y1: "6", x2: "6", y2: "18" }), u("line", { x1: "6", y1: "6", x2: "18", y2: "18" })] });
    }

    globalPlugins.push(index);

})(FullCalendar.Shared);
