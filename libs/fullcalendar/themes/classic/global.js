/*!
FullCalendar (Vanilla JS) v7.0.0
Docs & License: https://fullcalendar.io
(c) 2026 Adam Shaw
*/
(function ({ H: joinClassNames, u, G: globalPlugins }) {
    

    // usually 11px font / 12px line-height
    const xxsTextClass = "fc-classic-vQz";
    // outline
    const outlineWidthClass = "fc-classic-0Bj";
    const outlineWidthFocusClass = "fc-classic-uqo";
    const outlineOffsetClass = "fc-classic-3Xj";
    const outlineInsetClass = "fc-classic-fFh";
    const primaryOutlineColorClass = "fc-classic-zIi";
    // neutral buttons
    const strongSolidPressableClass = "fc-classic-BaR";
    const mutedHoverClass = "fc-classic-4yP";
    const mutedHoverPressableClass = `${mutedHoverClass} fc-classic-tCP fc-classic-8gz`;
    const faintHoverClass = "fc-classic-Ubk";
    const faintHoverPressableClass = `${faintHoverClass} fc-classic-OIx fc-classic-28F`;
    const buttonIconClass = "fc-classic-XUJ";
    // transparent resizer for mouse
    const blockPointerResizerClass = "fc-classic-1EY fc-classic-pps fc-classic-vs6";
    const rowPointerResizerClass = `${blockPointerResizerClass} fc-classic-AWB fc-classic-hza`;
    const columnPointerResizerClass = `${blockPointerResizerClass} fc-classic-MaV fc-classic-uuA`;
    // circle resizer for touch
    const blockTouchResizerClass = "fc-classic-1EY fc-classic-3wQ fc-classic-wsy fc-classic-lNM fc-classic-Jk3 fc-classic-AAA";
    const rowTouchResizerClass = `${blockTouchResizerClass} fc-classic-ERR fc-classic-Dq8`;
    const columnTouchResizerClass = `${blockTouchResizerClass} fc-classic-1V6 fc-classic-F99`;
    const getDayClass = (info) => joinClassNames("fc-classic-wsy", info.isMajor ? "fc-classic-C0k" : "fc-classic-C1x", info.isDisabled ? "fc-classic-iYS" :
        info.isToday && "fc-classic-hbn");
    const getSlotClass = (info) => joinClassNames("fc-classic-wsy fc-classic-C1x", info.isMinor && "fc-classic-TN2");
    const dayRowCommonClasses = {
        /* Day Row > List-Item Event
        ----------------------------------------------------------------------------------------------- */
        listItemEventClass: (info) => joinClassNames("fc-classic-Ika fc-classic-7A6 fc-classic-Fvv", info.isNarrow ? "fc-classic-148" : "fc-classic-cKZ", info.isSelected
            ? joinClassNames("fc-classic-k3f", info.isDragging && "fc-classic-qNs")
            : (info.isInteractive ? mutedHoverPressableClass : mutedHoverClass)),
        listItemEventBeforeClass: (info) => joinClassNames("fc-classic-Mjo", info.isNarrow ? "fc-classic-148" : "fc-classic-rVY"),
        listItemEventInnerClass: (info) => joinClassNames("fc-classic-dl1 fc-classic-1sP fc-classic-XpK fc-classic-z5u fc-classic-aTF", info.isNarrow ? xxsTextClass : "fc-classic-a3B"),
        listItemEventTimeClass: "fc-classic-F1o fc-classic-TZ4 fc-classic-pKG fc-classic-1Zl",
        listItemEventTitleClass: "fc-classic-F1o fc-classic-DIS fc-classic-TZ4 fc-classic-pKG fc-classic-OLq",
        /* Day Row > Row Event
        ----------------------------------------------------------------------------------------------- */
        rowEventClass: (info) => joinClassNames(info.isStart && joinClassNames("fc-classic-kmj", info.isNarrow ? "fc-classic-qvL" : "fc-classic-Jzj"), info.isEnd && joinClassNames("fc-classic-Skl", info.isNarrow ? "fc-classic-9hC" : "fc-classic-3e1")),
        rowEventInnerClass: "fc-classic-z5u fc-classic-aTF",
        rowEventTimeClass: "fc-classic-F1o",
        rowEventTitleClass: "fc-classic-F1o",
        /* Day Row > More-Link
        ----------------------------------------------------------------------------------------------- */
        rowMoreLinkClass: (info) => joinClassNames("fc-classic-Ika fc-classic-wsy fc-classic-Fvv", info.isNarrow
            ? "fc-classic-148 fc-classic-0Pr"
            : "fc-classic-sI7 fc-classic-cKZ fc-classic-d0j", mutedHoverPressableClass),
        rowMoreLinkInnerClass: (info) => joinClassNames("fc-classic-7A6", info.isNarrow ? xxsTextClass : "fc-classic-a3B"),
    };
    const expanderIconClass = "fc-classic-vnf fc-classic-mAY";
    const continuationArrowClass = "fc-classic-rVY fc-classic-XM3 fc-classic-rif fc-classic-lMo";
    var index = {
        name: "theme-classic",
        optionDefaults: {
            className: "fc-classic-yth fc-classic-n5m",
            viewClass: (info) => {
                const hasBorderTop = info.options.headerToolbar || !info.borderlessTop;
                const hasBorderBottom = info.options.footerToolbar || !info.borderlessBottom;
                const hasBorderX = !info.borderlessX;
                return joinClassNames("fc-classic-Jk3 fc-classic-GAX fc-classic-C1x", hasBorderTop && "fc-classic-ku3", hasBorderBottom && "fc-classic-zi1", hasBorderX && "fc-classic-1Wx");
            },
            /* Toolbar
            --------------------------------------------------------------------------------------------- */
            toolbarClass: (info) => joinClassNames("fc-classic-dl1 fc-classic-1sP fc-classic-dNl fc-classic-XpK fc-classic-N2M fc-classic-wwb", info.borderlessX && "fc-classic-Apf"),
            toolbarSectionClass: "fc-classic-yi0 fc-classic-dl1 fc-classic-1sP fc-classic-XpK fc-classic-wwb",
            toolbarTitleClass: "fc-classic-AVD fc-classic-DIS",
            buttonGroupClass: "fc-classic-dl1 fc-classic-1sP fc-classic-XpK",
            buttonClass: (info) => joinClassNames("fc-classic-dl6 fc-classic-1Wx fc-classic-dl1 fc-classic-1sP fc-classic-XpK fc-classic-sOR fc-classic-lYz fc-classic-vwH fc-classic-9yp fc-classic-RnT fc-classic-cfp fc-classic-Z9U", info.isIconOnly ? "fc-classic-Eaq" : "fc-classic-Apf", info.buttonGroup
                ? "fc-classic-uk6 fc-classic-Tuc"
                : "fc-classic-Ig4", info.isSelected
                ? "fc-classic-rQI fc-classic-Adi"
                : "fc-classic-vXO fc-classic-bqK fc-classic-aIH fc-classic-nQ5 fc-classic-JWq fc-classic-9Rj fc-classic-5ky", info.isDisabled && "fc-classic-Q3Z fc-classic-3Lc"),
            buttons: {
                prev: {
                    iconContent: () => chevronLeft(`${buttonIconClass} fc-classic-asP`),
                },
                next: {
                    iconContent: () => chevronLeft(`${buttonIconClass} fc-classic-jmT fc-classic-jY6`),
                },
                prevYear: {
                    iconContent: () => chevronsLeft(`${buttonIconClass} fc-classic-asP`),
                },
                nextYear: {
                    iconContent: () => chevronsLeft(`${buttonIconClass} fc-classic-jmT fc-classic-jY6`),
                },
            },
            /* Abstract Event
            --------------------------------------------------------------------------------------------- */
            eventColor: "var(--fc-classic-event)",
            eventContrastColor: "var(--fc-classic-event-contrast)",
            eventClass: (info) => joinClassNames(info.isDragging && "fc-classic-n5m", info.event.url && "fc-classic-JiE", info.isSelected
                ? joinClassNames(outlineWidthClass, info.isDragging ? "fc-classic-1kP" : "fc-classic-tkw")
                : outlineWidthFocusClass, primaryOutlineColorClass),
            /* Background Event
            --------------------------------------------------------------------------------------------- */
            backgroundEventColor: "var(--fc-classic-background-event)",
            backgroundEventClass: "fc-classic-hsC fc-classic-jsy fc-classic-DO7",
            backgroundEventTitleClass: (info) => joinClassNames("fc-classic-MGT fc-classic-L1Y", info.isNarrow
                ? `fc-classic-KUX ${xxsTextClass}`
                : "fc-classic-XJa fc-classic-a3B"),
            /* List-Item Event
            --------------------------------------------------------------------------------------------- */
            listItemEventClass: "fc-classic-XpK",
            listItemEventBeforeClass: "fc-classic-lNM fc-classic-AAA",
            listItemEventInnerClass: "fc-classic-GAX",
            /* Block Event
            --------------------------------------------------------------------------------------------- */
            blockEventClass: (info) => joinClassNames("fc-classic-bCs fc-classic-eYX fc-classic-d0j fc-classic-DO7 fc-classic-YjJ fc-classic-vwH", (info.isDragging && !info.isSelected) && "fc-classic-iTG", outlineOffsetClass),
            blockEventInnerClass: "fc-classic-i9F fc-classic-cfp",
            blockEventTimeClass: "fc-classic-TZ4 fc-classic-pKG fc-classic-1Zl",
            blockEventTitleClass: "fc-classic-TZ4 fc-classic-pKG fc-classic-OLq",
            /* Row Event
            --------------------------------------------------------------------------------------------- */
            rowEventClass: (info) => joinClassNames("fc-classic-Ika fc-classic-JIC", info.isStart && "fc-classic-3J4", info.isEnd && "fc-classic-USt"),
            rowEventBeforeClass: (info) => joinClassNames(info.isStartResizable && joinClassNames(info.isSelected ? rowTouchResizerClass : rowPointerResizerClass, "fc-classic-11a")),
            rowEventAfterClass: (info) => joinClassNames(info.isEndResizable && joinClassNames(info.isSelected ? rowTouchResizerClass : rowPointerResizerClass, "fc-classic-bEw")),
            rowEventInnerClass: (info) => joinClassNames("fc-classic-dl1 fc-classic-1sP fc-classic-XpK", info.isNarrow ? xxsTextClass : "fc-classic-a3B"),
            rowEventTimeClass: "fc-classic-DIS",
            /* Column Event
            --------------------------------------------------------------------------------------------- */
            columnEventClass: (info) => joinClassNames("fc-classic-1Wx fc-classic-A3h fc-classic-yKG", info.isStart && "fc-classic-ku3 fc-classic-Z7Q", info.isEnd && "fc-classic-Ika fc-classic-zi1 fc-classic-2qh"),
            columnEventBeforeClass: (info) => joinClassNames(info.isStartResizable && joinClassNames(info.isSelected ? columnTouchResizerClass : columnPointerResizerClass, "fc-classic-YDC")),
            columnEventAfterClass: (info) => joinClassNames(info.isEndResizable && joinClassNames(info.isSelected ? columnTouchResizerClass : columnPointerResizerClass, "fc-classic-fJL")),
            columnEventInnerClass: (info) => joinClassNames("fc-classic-dl1", info.isShort
                ? "fc-classic-KUX fc-classic-1sP fc-classic-XpK fc-classic-NWN"
                : "fc-classic-oQ2 fc-classic-sgX"),
            columnEventTimeClass: (info) => joinClassNames(!info.isShort && "fc-classic-166", xxsTextClass),
            columnEventTitleClass: (info) => joinClassNames(!info.isShort && "fc-classic-2rx", (info.isShort || info.isNarrow) ? xxsTextClass : "fc-classic-a3B"),
            /* More-Link
            --------------------------------------------------------------------------------------------- */
            moreLinkClass: `${outlineWidthFocusClass} ${primaryOutlineColorClass}`,
            moreLinkInnerClass: "fc-classic-TZ4 fc-classic-pKG",
            columnMoreLinkClass: `fc-classic-Ika fc-classic-Fvv fc-classic-wsy fc-classic-d0j fc-classic-4MR ${strongSolidPressableClass} fc-classic-vwH fc-classic-A3h fc-classic-yKG ${outlineOffsetClass}`,
            columnMoreLinkInnerClass: (info) => joinClassNames("fc-classic-KUX", info.isNarrow ? xxsTextClass : "fc-classic-a3B"),
            /* Day Header
            --------------------------------------------------------------------------------------------- */
            dayHeaderAlign: (info) => info.inPopover ? "start" : "center",
            dayHeaderClass: (info) => joinClassNames("fc-classic-E9P", info.isDisabled && "fc-classic-iYS", info.inPopover
                ? "fc-classic-zi1 fc-classic-C1x fc-classic-k3f"
                : joinClassNames("fc-classic-wsy", info.isMajor ? "fc-classic-C0k" : "fc-classic-C1x")),
            dayHeaderInnerClass: (info) => joinClassNames("fc-classic-rVY fc-classic-cJ3 fc-classic-dl1 fc-classic-sgX", info.isNarrow ? xxsTextClass : "fc-classic-9yp"),
            dayHeaderDividerClass: "fc-classic-zi1 fc-classic-C1x",
            /* Day Cell
            --------------------------------------------------------------------------------------------- */
            dayCellClass: getDayClass,
            dayCellTopClass: (info) => joinClassNames(info.isNarrow ? "fc-classic-toR" : "fc-classic-84e", "fc-classic-dl1 fc-classic-1sP fc-classic-LMv"),
            dayCellTopInnerClass: (info) => joinClassNames("fc-classic-rVY fc-classic-TZ4", info.isNarrow
                ? `fc-classic-cJ3 ${xxsTextClass}`
                : "fc-classic-V9v fc-classic-9yp", info.isOther && "fc-classic-taq", info.monthText && "fc-classic-DIS"),
            dayCellInnerClass: (info) => joinClassNames(info.inPopover && "fc-classic-3N5"),
            /* Popover
            --------------------------------------------------------------------------------------------- */
            popoverClass: "fc-classic-Jk3 fc-classic-GAX fc-classic-wsy fc-classic-C1x fc-classic-tkw fc-classic-aNc fc-classic-n5m",
            popoverCloseClass: `fc-classic-bCs fc-classic-1EY fc-classic-2ik fc-classic-2w8 ${outlineWidthFocusClass} ${primaryOutlineColorClass} fc-classic-Z9U`,
            popoverCloseContent: () => x("fc-classic-XUJ fc-classic-9yp fc-classic-mAY"),
            /* Lane
            --------------------------------------------------------------------------------------------- */
            dayLaneClass: getDayClass,
            dayLaneInnerClass: (info) => (info.isStack
                ? "fc-classic-gMS"
                : info.isNarrow ? "fc-classic-148" : "fc-classic-Jzj fc-classic-B3G"),
            slotLaneClass: getSlotClass,
            /* List Day
            --------------------------------------------------------------------------------------------- */
            listDayHeaderClass: "fc-classic-zi1 fc-classic-C1x fc-classic-SDU fc-classic-nHS fc-classic-dl1 fc-classic-1sP fc-classic-XpK fc-classic-N2M",
            listDayHeaderInnerClass: "fc-classic-Apf fc-classic-dl6 fc-classic-9yp fc-classic-DIS",
            /* Single Month (in Multi-Month)
            --------------------------------------------------------------------------------------------- */
            singleMonthClass: (info) => joinClassNames(info.multiMonthColumns > 1 && "fc-classic-jD5", (info.multiMonthColumns === 1 && !info.isLast) && "fc-classic-zi1 fc-classic-C1x"),
            singleMonthHeaderClass: (info) => joinClassNames(info.multiMonthColumns > 1
                ? "fc-classic-cM0"
                : "fc-classic-dl6 fc-classic-zi1 fc-classic-C1x fc-classic-Jk3", "fc-classic-XpK"),
            singleMonthHeaderInnerClass: "fc-classic-1Po fc-classic-DIS",
            /* Misc Table
            --------------------------------------------------------------------------------------------- */
            tableHeaderClass: "fc-classic-Jk3",
            fillerClass: "fc-classic-wsy fc-classic-C1x fc-classic-lMo",
            dayHeaderRowClass: "fc-classic-wsy fc-classic-C1x",
            dayRowClass: "fc-classic-wsy fc-classic-C1x",
            slotHeaderRowClass: "fc-classic-wsy fc-classic-C1x",
            slotHeaderClass: getSlotClass,
            /* Misc Content
            --------------------------------------------------------------------------------------------- */
            navLinkClass: `fc-classic-Eu0 ${outlineWidthFocusClass} ${outlineInsetClass} ${primaryOutlineColorClass}`,
            inlineWeekNumberClass: (info) => joinClassNames("fc-classic-1EY fc-classic-n9G fc-classic-rbS fc-classic-C2g fc-classic-KUX fc-classic-HXA fc-classic-m9h fc-classic-k3f", info.isNarrow ? xxsTextClass : "fc-classic-9yp"),
            nonBusinessHoursClass: "fc-classic-iYS",
            highlightClass: "fc-classic-hLU",
            /* Resource Day Header
            --------------------------------------------------------------------------------------------- */
            resourceDayHeaderAlign: "center",
            resourceDayHeaderClass: (info) => joinClassNames("fc-classic-wsy", info.isMajor ? "fc-classic-C0k" : "fc-classic-C1x"),
            resourceDayHeaderInnerClass: (info) => joinClassNames("fc-classic-rVY fc-classic-cJ3 fc-classic-dl1 fc-classic-sgX", info.isNarrow ? xxsTextClass : "fc-classic-9yp"),
            /* Resource Data Grid
            --------------------------------------------------------------------------------------------- */
            resourceColumnHeaderClass: "fc-classic-wsy fc-classic-C1x fc-classic-E9P",
            resourceColumnHeaderInnerClass: "fc-classic-bvX fc-classic-9yp",
            resourceColumnResizerClass: "fc-classic-1EY fc-classic-AWB fc-classic-4Tv fc-classic-dnf",
            resourceGroupHeaderClass: "fc-classic-wsy fc-classic-C1x fc-classic-k3f",
            resourceGroupHeaderInnerClass: "fc-classic-bvX fc-classic-9yp",
            resourceCellClass: "fc-classic-wsy fc-classic-C1x",
            resourceCellInnerClass: "fc-classic-bvX fc-classic-9yp",
            resourceIndentClass: "fc-classic-Mde fc-classic-kp0 fc-classic-E9P",
            resourceExpanderClass: `fc-classic-bCs ${outlineWidthFocusClass} ${primaryOutlineColorClass}`,
            resourceExpanderContent: (info) => info.isExpanded
                ? minusSquare(expanderIconClass)
                : plusSquare(expanderIconClass),
            resourceHeaderRowClass: "fc-classic-wsy fc-classic-C1x",
            resourceRowClass: "fc-classic-wsy fc-classic-C1x",
            resourceColumnDividerClass: "fc-classic-1Wx fc-classic-C1x fc-classic-a7i fc-classic-k3f",
            /* Timeline Lane
            --------------------------------------------------------------------------------------------- */
            resourceGroupLaneClass: "fc-classic-wsy fc-classic-C1x fc-classic-k3f",
            resourceLaneClass: "fc-classic-wsy fc-classic-C1x",
            resourceLaneBottomClass: (info) => info.options.eventOverlap && "fc-classic-zrJ",
            timelineBottomClass: "fc-classic-zrJ",
        },
        views: {
            dayGrid: {
                ...dayRowCommonClasses,
                dayCellBottomClass: "fc-classic-toR",
            },
            multiMonth: {
                ...dayRowCommonClasses,
                dayCellBottomClass: "fc-classic-toR",
                tableClass: (info) => joinClassNames(info.multiMonthColumns > 1 && "fc-classic-C1x fc-classic-wsy"),
            },
            timeGrid: {
                ...dayRowCommonClasses,
                dayCellBottomClass: "fc-classic-mhE",
                /* TimeGrid > Week Number Header
                ------------------------------------------------------------------------------------------- */
                weekNumberHeaderClass: "fc-classic-XpK fc-classic-LMv",
                weekNumberHeaderInnerClass: (info) => joinClassNames("fc-classic-rVY fc-classic-cJ3", info.isNarrow ? xxsTextClass : "fc-classic-9yp"),
                /* TimeGrid > All-Day Header
                ------------------------------------------------------------------------------------------- */
                allDayHeaderClass: "fc-classic-XpK fc-classic-LMv",
                allDayHeaderInnerClass: (info) => joinClassNames("fc-classic-rVY fc-classic-2tF fc-classic-2HE", info.isNarrow ? xxsTextClass : "fc-classic-9yp"),
                allDayDividerClass: "fc-classic-JIC fc-classic-C1x fc-classic-8ub fc-classic-k3f",
                /* TimeGrid > Slot Header
                ------------------------------------------------------------------------------------------- */
                slotHeaderClass: "fc-classic-LMv",
                slotHeaderInnerClass: (info) => joinClassNames("fc-classic-rVY fc-classic-cJ3", info.isNarrow ? xxsTextClass : "fc-classic-9yp"),
                slotHeaderDividerClass: "fc-classic-USt fc-classic-C1x",
                /* TimeGrid > Now-Indicator
                ------------------------------------------------------------------------------------------- */
                nowIndicatorHeaderClass: "fc-classic-rbS fc-classic-a10 fc-classic-XM3 fc-classic-rif fc-classic-jIH fc-classic-0qY",
                nowIndicatorLineClass: "fc-classic-ku3 fc-classic-sYT",
            },
            list: {
                /* List-View > List-Item Event
                ------------------------------------------------------------------------------------------- */
                listDayClass: (info) => joinClassNames(!info.isLast && "fc-classic-zi1 fc-classic-C1x"),
                listItemEventClass: (info) => joinClassNames("fc-classic-bCs fc-classic-Apf fc-classic-dl6 fc-classic-wwb fc-classic-ku3 fc-classic-C1x", info.isInteractive
                    ? joinClassNames(faintHoverPressableClass, outlineInsetClass)
                    : faintHoverClass),
                listItemEventBeforeClass: "fc-classic-GOm",
                listItemEventInnerClass: "fc-classic-eF2",
                listItemEventTimeClass: "fc-classic-88I fc-classic-yi0 fc-classic-roZ fc-classic-kMV fc-classic-TZ4 fc-classic-pKG fc-classic-IPx fc-classic-9yp",
                listItemEventTitleClass: (info) => joinClassNames("fc-classic-1El fc-classic-2KU fc-classic-TZ4 fc-classic-pKG fc-classic-9yp", info.event.url && "fc-classic-Ogp"),
                /* No-Events Screen
                ------------------------------------------------------------------------------------------- */
                noEventsClass: "fc-classic-k3f fc-classic-dl1 fc-classic-sgX fc-classic-XpK fc-classic-E9P",
                noEventsInnerClass: "sticky fc-classic-jGI fc-classic-P9h",
            },
            timeline: {
                /* Timeline > Row Event
                ------------------------------------------------------------------------------------------- */
                rowEventClass: (info) => joinClassNames(info.isEnd && "fc-classic-9hC", "fc-classic-XpK"),
                rowEventBeforeClass: (info) => (!info.isStart && `${continuationArrowClass} fc-classic-Bda fc-classic-5JV`),
                rowEventAfterClass: (info) => (!info.isEnd && `${continuationArrowClass} fc-classic-hhi fc-classic-LaM`),
                rowEventInnerClass: (info) => (info.options.eventOverlap
                    ? "fc-classic-2rx"
                    : "fc-classic-End"),
                rowEventTimeClass: "fc-classic-oQ2",
                rowEventTitleClass: "fc-classic-oQ2",
                /* Timeline > More-Link
                ------------------------------------------------------------------------------------------- */
                rowMoreLinkClass: `fc-classic-9hC fc-classic-Ika fc-classic-wsy fc-classic-d0j fc-classic-4MR ${strongSolidPressableClass} fc-classic-vwH`,
                rowMoreLinkInnerClass: "fc-classic-KUX fc-classic-a3B",
                /* Timeline > Slot Header
                ------------------------------------------------------------------------------------------- */
                slotHeaderAlign: (info) => info.isTime ? "start" : "center",
                slotHeaderClass: (info) => joinClassNames("fc-classic-E9P", !info.level && "fc-classic-pKG"),
                slotHeaderInnerClass: (info) => joinClassNames("fc-classic-fn8 fc-classic-V9v fc-classic-9yp", info.hasNavLink && "fc-classic-Eu0"),
                slotHeaderDividerClass: "fc-classic-zi1 fc-classic-C1x",
                /* Timeline > Now-Indicator
                ------------------------------------------------------------------------------------------- */
                nowIndicatorHeaderClass: "fc-classic-n9G fc-classic-J04 fc-classic-ybF fc-classic-Pqk fc-classic-bLA fc-classic-sYT",
                nowIndicatorLineClass: "fc-classic-3J4 fc-classic-sYT",
            },
        }
    };
    /* SVGs
    ------------------------------------------------------------------------------------------------- */
    function chevronLeft(className) {
        return u("svg", { xmlns: "http://www.w3.org/2000/svg", className: className, width: "20", height: "20", viewBox: "0 0 24 24", fill: "none", stroke: "currentColor", strokeWidth: "2", strokeLinecap: "round", strokeLinejoin: "round", children: u("polyline", { points: "15 18 9 12 15 6" }) });
    }
    function chevronsLeft(className) {
        return u("svg", { xmlns: "http://www.w3.org/2000/svg", className: className, width: "20", height: "20", viewBox: "0 0 24 24", fill: "none", stroke: "currentColor", strokeWidth: "2", strokeLinecap: "round", strokeLinejoin: "round", children: [u("polyline", { points: "11 17 6 12 11 7" }), u("polyline", { points: "18 17 13 12 18 7" })] });
    }
    function x(className) {
        return u("svg", { xmlns: "http://www.w3.org/2000/svg", className: className, width: "20", height: "20", viewBox: "0 0 24 24", fill: "none", stroke: "currentColor", strokeWidth: "2", strokeLinecap: "round", strokeLinejoin: "round", children: [u("line", { x1: "18", y1: "6", x2: "6", y2: "18" }), u("line", { x1: "6", y1: "6", x2: "18", y2: "18" })] });
    }
    function plusSquare(className) {
        return u("svg", { xmlns: "http://www.w3.org/2000/svg", className: className, width: "20", height: "20", viewBox: "0 0 24 24", fill: "none", stroke: "currentColor", strokeWidth: "2", strokeLinecap: "round", strokeLinejoin: "round", children: [u("rect", { x: "3", y: "3", width: "18", height: "18", rx: "2", ry: "2" }), u("line", { x1: "12", y1: "8", x2: "12", y2: "16" }), u("line", { x1: "8", y1: "12", x2: "16", y2: "12" })] });
    }
    function minusSquare(className) {
        return u("svg", { xmlns: "http://www.w3.org/2000/svg", className: className, width: "20", height: "20", viewBox: "0 0 24 24", fill: "none", stroke: "currentColor", strokeWidth: "2", strokeLinecap: "round", strokeLinejoin: "round", children: [u("rect", { x: "3", y: "3", width: "18", height: "18", rx: "2", ry: "2" }), u("line", { x1: "8", y1: "12", x2: "16", y2: "12" })] });
    }

    globalPlugins.push(index);

})(FullCalendar.Shared);
