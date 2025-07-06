/*!
FullCalendar RRule Plugin v6.1.17
Docs & License: https://fullcalendar.io/docs/rrule-plugin
(c) 2024 Adam Shaw
*/
FullCalendar.RRule = (function (exports, core, rruleLib, internal) {
    'use strict';

    function _interopNamespace(e) {
        if (e && e.__esModule) return e;
        var n = Object.create(null);
        if (e) {
            Object.keys(e).forEach(function (k) {
                if (k !== 'default') {
                    var d = Object.getOwnPropertyDescriptor(e, k);
                    Object.defineProperty(n, k, d.get ? d : {
                        enumerable: true,
                        get: function () { return e[k]; }
                    });
                }
            });
        }
        n["default"] = e;
        return n;
    }

    var rruleLib__namespace = /*#__PURE__*/_interopNamespace(rruleLib);

    const recurringType = {
        parse(eventProps, dateEnv) {
            if (eventProps.rrule != null) {
                let eventRRuleData = parseEventRRule(eventProps, dateEnv);
                if (eventRRuleData) {
                    return {
                        typeData: {
                            rruleSet: eventRRuleData.rruleSet,
                            dateEnv: eventRRuleData.isTimeZoneSpecified ? undefined : dateEnv,
                        },
                        allDayGuess: !eventRRuleData.isTimeSpecified,
                        duration: eventProps.duration,
                    };
                }
            }
            return null;
        },
        expand(eventRRuleData, framingRange, calendarDateEnv) {
            return eventRRuleData.rruleSet.between(
            // Add one-day leeway since rrule lib only operates in UTC,
            // but the zoned variant of framingRange is not.
            // Also overcomes this rrule bug:
            // https://github.com/jakubroztocil/rrule/issues/84)
            internal.addDays(framingRange.start, -1), internal.addDays(framingRange.end, 1)).map((date) => {
                // convert to plain-datetime
                return calendarDateEnv.createMarker(
                // convert to epoch-milliseconds in original timezone
                eventRRuleData.dateEnv
                    ? eventRRuleData.dateEnv.toDate(date)
                    : date);
            });
        },
    };
    function parseEventRRule(eventProps, dateEnv) {
        let rruleSet;
        let isTimeSpecified = false;
        let isTimeZoneSpecified = false;
        if (typeof eventProps.rrule === 'string') {
            let res = parseRRuleString(eventProps.rrule);
            rruleSet = res.rruleSet;
            isTimeSpecified = res.isTimeSpecified;
            isTimeZoneSpecified = res.isTimeZoneSpecified;
        }
        if (typeof eventProps.rrule === 'object' && eventProps.rrule) { // non-null object
            let res = parseRRuleObject(eventProps.rrule, dateEnv);
            rruleSet = new rruleLib__namespace.RRuleSet();
            rruleSet.rrule(res.rrule);
            isTimeSpecified = res.isTimeSpecified;
            isTimeZoneSpecified = res.isTimeZoneSpecified;
        }
        // convery to arrays. TODO: general util?
        let exdateInputs = [].concat(eventProps.exdate || []);
        let exruleInputs = [].concat(eventProps.exrule || []);
        for (let exdateInput of exdateInputs) {
            let res = internal.parseMarker(exdateInput);
            isTimeSpecified = isTimeSpecified || !res.isTimeUnspecified;
            isTimeZoneSpecified = isTimeZoneSpecified || res.timeZoneOffset !== null;
            rruleSet.exdate(new Date(res.marker.valueOf() - (res.timeZoneOffset || 0) * 60 * 1000));
        }
        // TODO: exrule is deprecated. what to do? (https://icalendar.org/iCalendar-RFC-5545/a-3-deprecated-features.html)
        for (let exruleInput of exruleInputs) {
            let res = parseRRuleObject(exruleInput, dateEnv);
            isTimeSpecified = isTimeSpecified || res.isTimeSpecified;
            isTimeZoneSpecified = isTimeZoneSpecified || res.isTimeZoneSpecified;
            rruleSet.exrule(res.rrule);
        }
        return { rruleSet, isTimeSpecified, isTimeZoneSpecified };
    }
    function parseRRuleObject(rruleInput, dateEnv) {
        let isTimeSpecified = false;
        let isTimeZoneSpecified = false;
        function processDateInput(dateInput) {
            if (typeof dateInput === 'string') {
                let markerData = internal.parseMarker(dateInput);
                if (markerData) {
                    isTimeSpecified = isTimeSpecified || !markerData.isTimeUnspecified;
                    isTimeZoneSpecified = isTimeZoneSpecified || markerData.timeZoneOffset !== null;
                    return new Date(markerData.marker.valueOf() - (markerData.timeZoneOffset || 0) * 60 * 1000); // NOT DRY
                }
                return null;
            }
            return dateInput; // TODO: what about number timestamps?
        }
        let rruleOptions = Object.assign(Object.assign({}, rruleInput), { dtstart: processDateInput(rruleInput.dtstart), until: processDateInput(rruleInput.until), freq: convertConstant(rruleInput.freq), wkst: rruleInput.wkst == null
                ? (dateEnv.weekDow - 1 + 7) % 7 // convert Sunday-first to Monday-first
                : convertConstant(rruleInput.wkst), byweekday: convertConstants(rruleInput.byweekday) });
        return { rrule: new rruleLib__namespace.RRule(rruleOptions), isTimeSpecified, isTimeZoneSpecified };
    }
    function parseRRuleString(str) {
        let rruleSet = rruleLib__namespace.rrulestr(str, { forceset: true });
        let analysis = analyzeRRuleString(str);
        return Object.assign({ rruleSet }, analysis);
    }
    function analyzeRRuleString(str) {
        let isTimeSpecified = false;
        let isTimeZoneSpecified = false;
        function processMatch(whole, introPart, datePart) {
            let result = internal.parseMarker(datePart);
            isTimeSpecified = isTimeSpecified || !result.isTimeUnspecified;
            isTimeZoneSpecified = isTimeZoneSpecified || result.timeZoneOffset !== null;
        }
        str.replace(/\b(DTSTART:)([^\n]*)/, processMatch);
        str.replace(/\b(EXDATE:)([^\n]*)/, processMatch);
        str.replace(/\b(UNTIL=)([^;\n]*)/, processMatch);
        return { isTimeSpecified, isTimeZoneSpecified };
    }
    function convertConstants(input) {
        if (Array.isArray(input)) {
            return input.map(convertConstant);
        }
        return convertConstant(input);
    }
    function convertConstant(input) {
        if (typeof input === 'string') {
            return rruleLib__namespace.RRule[input.toUpperCase()];
        }
        return input;
    }

    const RRULE_EVENT_REFINERS = {
        rrule: internal.identity,
        exrule: internal.identity,
        exdate: internal.identity,
        duration: internal.createDuration,
    };

    var plugin = core.createPlugin({
        name: '@fullcalendar/rrule',
        recurringTypes: [recurringType],
        eventRefiners: RRULE_EVENT_REFINERS,
    });

    core.globalPlugins.push(plugin);

    exports["default"] = plugin;

    Object.defineProperty(exports, '__esModule', { value: true });

    return exports;

})({}, FullCalendar, rrule, FullCalendar.Internal);
