// The calendar page (agent/calendar.php).
//
// Items are fetched for the range on screen from ajax.php?calendar_items -
// the events on the calendars ticked, and the built-in sources ticked - for
// one client or all, everyone's or one agent's. What is ticked, whose, and
// the view are remembered in this browser.
//
// Picking a day or a time range opens New Event for it. A click opens the
// item's quick look. Scheduled tickets and events that do not repeat can be
// dragged to a new time (ajax.php?calendar_move), and the open tickets in To
// schedule dragged onto the calendar to book them - a ticket is booked exactly
// as from the ticket, so the agent and contact are emailed the time.

(function () {

    const root = document.getElementById('itflowCalendar');
    const el = document.getElementById('calendar');
    if (!root || !el || typeof FullCalendar === 'undefined') {
        return;
    }

    const csrf = root.dataset.csrf;
    const clientId = Number(root.dataset.clientId) || 0;
    const myId = Number(root.dataset.userId) || 0;
    const canSchedule = root.dataset.canSchedule === '1';
    // A client's calendar takes Clients at Modify to add to (calendarEventCan())
    const canAdd = root.dataset.canAdd !== '0';
    const mobile = root.dataset.mobile === '1' || window.innerWidth < 768;
    const sourceDefaults = JSON.parse(root.dataset.sources || '{}');
    const agentSelect = document.getElementById('calendarAgent');
    const unscheduledList = document.getElementById('calendarUnscheduled');

    // ---------- what this browser remembers ----------

    const storeKey = 'itflow.calendar.' + (clientId ? 'client' : 'all');
    let state = {};
    try {
        state = JSON.parse(window.localStorage.getItem(storeKey) || '{}') || {};
    } catch (e) {
        state = {};
    }
    function save() {
        try {
            window.localStorage.setItem(storeKey, JSON.stringify(state));
        } catch (e) {
            // Private window or storage blocked - it just won't be remembered
        }
    }

    state.hidden = Array.isArray(state.hidden) ? state.hidden : [];
    state.sources = state.sources && typeof state.sources === 'object' ? state.sources : {};

    root.querySelectorAll('.itflow-cal-check[data-calendar]').forEach(function (box) {
        box.checked = state.hidden.indexOf(box.dataset.calendar) === -1;
    });
    root.querySelectorAll('.itflow-cal-check[data-source]').forEach(function (box) {
        const key = box.dataset.source;
        box.checked = key in state.sources ? !!state.sources[key] : !!(sourceDefaults[key] || {}).default;
    });
    if (agentSelect && state.agent && agentSelect.querySelector('option[value="' + Number(state.agent) + '"]')) {
        agentSelect.value = String(Number(state.agent));
    }

    function shownCalendars() {
        return Array.from(root.querySelectorAll('.itflow-cal-check[data-calendar]:checked')).map(function (box) {
            return box.dataset.calendar;
        });
    }
    function shownSources() {
        return Array.from(root.querySelectorAll('.itflow-cal-check[data-source]:checked')).map(function (box) {
            return box.dataset.source;
        });
    }
    function agentId() {
        return agentSelect ? Number(agentSelect.value) || 0 : 0;
    }

    // ---------- helpers ----------

    const pad = function (n) {
        return String(n).padStart(2, '0');
    };
    // Local wall clock - toISOString() would shift by the browser's offset
    function ymd(date) {
        return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
    }
    function ymdhm(date) {
        return ymd(date) + ' ' + pad(date.getHours()) + ':' + pad(date.getMinutes()) + ':00';
    }
    function words(date, allDay) {
        const options = {weekday: 'short', month: 'short', day: 'numeric'};
        if (!allDay) {
            options.hour = 'numeric';
            options.minute = '2-digit';
        }
        return date.toLocaleString(undefined, options);
    }

    function openModal(url, size) {
        const link = document.createElement('a');
        link.href = '#';
        link.className = 'ajax-modal d-none';
        link.dataset.modalUrl = url;
        if (size) {
            link.dataset.modalSize = size;
        }
        document.body.appendChild(link);
        link.click();
        link.remove();
    }

    function newEvent(params) {
        const query = new URLSearchParams(params || {});
        if (clientId) {
            query.set('client_id', clientId);
            query.set('lock_client', '1');
        }
        openModal('modals/calendar/calendar_event_add.php?' + query.toString(), 'lg');
    }

    function confirmDialog(options) {
        if (typeof Swal === 'undefined') {
            return Promise.resolve({isConfirmed: window.confirm(options.title), isDenied: false});
        }
        return Swal.fire(Object.assign({
            theme: document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light',
            icon: 'question',
            showCancelButton: true,
            cancelButtonText: 'Cancel',
            reverseButtons: true,
            buttonsStyling: false,
            customClass: {
                confirmButton: 'btn btn-primary mx-1',
                denyButton: 'btn btn-outline-secondary mx-1',
                cancelButton: 'btn btn-secondary mx-1',
                input: 'itflow-cal-swal-input'
            }
        }, options));
    }

    function post(data, done, fail) {
        data.calendar_move = 1;
        data.csrf_token = csrf;
        fetch('ajax.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            credentials: 'same-origin',
            body: new URLSearchParams(data).toString()
        }).then(function (res) {
            return res.json().catch(function () {
                return {error: 'Something went wrong - reload and try again.'};
            });
        }).then(function (json) {
            if (json && json.ok) {
                done(json);
            } else {
                fail(json && json.error ? json.error : 'That could not be saved.');
            }
        }).catch(function () {
            fail('That could not be saved - check the connection.');
        });
    }

    function saved(json) {
        itflowToast(json.message, 'success');
        if (json.conflicts && json.conflicts.length) {
            itflowToast('Same agent, within two hours: ' + json.conflicts.join('; '), 'warning');
        }
    }

    // ---------- the calendar ----------

    const calendar = new FullCalendar.Calendar(el, {
        initialView: state.view || (mobile ? 'listWeek' : 'dayGridMonth'),
        initialDate: root.dataset.initialDate || undefined,
        headerToolbar: mobile
            ? {left: 'prev,next', center: 'title', right: 'today'}
            : {left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'},
        footerToolbar: mobile ? {center: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'} : false,
        noEventsText: 'Nothing on the calendar for these days',
        firstDay: Number(root.dataset.firstDay) || 0,
        height: mobile ? 'auto' : 'calc(100vh - 11rem)',
        navLinks: true,
        nowIndicator: true,
        dayMaxEvents: true,
        selectable: canAdd,
        selectMirror: true,
        editable: true,
        droppable: canSchedule,
        scrollTime: '07:30:00',
        businessHours: {daysOfWeek: [1, 2, 3, 4, 5], startTime: '08:00', endTime: '18:00'},
        eventOrder: 'allDay,start,-duration,title',
        views: {
            // Side by side past three, the rest go behind "+N more"
            timeGrid: {eventMaxStack: 3}
        },

        events: function (info, success, failure) {
            const end = new Date(info.end.getTime());
            end.setDate(end.getDate() - 1);
            itflowGet('ajax.php', {
                calendar_items: 1,
                from: ymd(info.start),
                to: ymd(end),
                calendars: shownCalendars().join(','),
                sources: shownSources().join(','),
                client_id: clientId,
                user_id: agentId()
            }, function (text) {
                let items = [];
                try {
                    items = JSON.parse(text);
                } catch (e) {
                    items = [];
                }
                success(Array.isArray(items) ? items : []);
            }, function (error) {
                failure(error);
            });
        },

        loading: function (on) {
            root.classList.toggle('is-loading', on);
        },

        datesSet: function (info) {
            state.view = info.view.type;
            save();
        },

        // An icon for what it is, the title, and where / who underneath when there is room
        eventContent: function (arg) {
            const props = arg.event.extendedProps || {};
            const view = arg.view.type;
            const wrap = document.createElement('div');
            wrap.className = 'itflow-cal-event ' + (view.indexOf('list') === 0 ? 'is-list' : (view.indexOf('timeGrid') === 0 && !arg.event.allDay ? 'is-timegrid' : 'is-daygrid'));
            if ((arg.timeText && !arg.event.allDay) || (arg.event.allDay && view.indexOf('list') === 0)) {
                const time = document.createElement('span');
                time.className = 'itflow-cal-time';
                time.textContent = arg.event.allDay ? 'All day' : arg.timeText;
                wrap.appendChild(time);
            }
            if (props.icon) {
                const icon = document.createElement('i');
                icon.className = 'fas fa-fw ' + props.icon + ' itflow-cal-icon';
                wrap.appendChild(icon);
            }
            const title = document.createElement('span');
            title.className = 'itflow-cal-title';
            title.textContent = arg.event.title;
            wrap.appendChild(title);
            if (props.detail && (view.indexOf('timeGrid') === 0 || view.indexOf('list') === 0)) {
                const detail = document.createElement('div');
                detail.className = 'itflow-cal-detail';
                detail.textContent = props.detail;
                wrap.appendChild(detail);
            }
            return {domNodes: [wrap]};
        },

        eventDidMount: function (info) {
            const props = info.event.extendedProps || {};
            info.el.setAttribute('title', info.event.title + (props.detail ? '\n' + props.detail : ''));
            info.el.dataset.kind = props.kind || '';
        },

        eventClick: function (info) {
            info.jsEvent.preventDefault();
            const props = info.event.extendedProps || {};
            if (props.modal) {
                openModal(props.modal, 'lg');
            } else if (props.url) {
                window.location.href = props.url;
            }
        },

        // A day, or a run of days or times, picked on empty calendar
        select: function (info) {
            calendar.unselect();
            newEvent({
                start: info.allDay ? ymd(info.start) : ymdhm(info.start),
                end: info.allDay ? ymd(info.end) : ymdhm(info.end),
                all_day: info.allDay ? 1 : 0
            });
        },

        eventDrop: function (info) {
            moved(info);
        },
        eventResize: function (info) {
            moved(info);
        },

        // A ticket from To schedule
        drop: function (info) {
            booked(info);
        }
    });

    calendar.render();

    // ---------- moving things ----------

    function moved(info) {
        const event = info.event;
        const props = event.extendedProps || {};
        const start = event.start;
        const end = event.end;

        if (props.kind === 'ticket') {
            confirmDialog({
                title: 'Move ' + event.title.split(' ')[0] + ' to ' + words(start, false) + '?',
                text: 'The agent and the contact are emailed the new time, as when it is rescheduled on the ticket.',
                confirmButtonText: 'Move it'
            }).then(function (result) {
                if (!result.isConfirmed) {
                    info.revert();
                    return;
                }
                post({kind: 'ticket', id: props.ticket_id, start: ymdhm(start), all_day: event.allDay ? 1 : 0}, function (json) {
                    saved(json);
                    calendar.refetchEvents();
                }, function (error) {
                    itflowToast(error, 'error');
                    info.revert();
                });
            });
            return;
        }

        if (props.kind !== 'event') {
            info.revert();
            return;
        }

        const send = function (notify) {
            post({
                kind: 'event',
                id: props.event_id,
                start: event.allDay ? ymd(start) + ' 00:00:00' : ymdhm(start),
                end: end ? (event.allDay ? ymd(end) + ' 00:00:00' : ymdhm(end)) : '',
                all_day: event.allDay ? 1 : 0,
                notify: notify ? 1 : 0
            }, function (json) {
                saved(json);
                calendar.refetchEvents();
            }, function (error) {
                itflowToast(error, 'error');
                info.revert();
            });
        };

        // The people who have it in their calendar can be sent the change
        if (props.invited > 0) {
            confirmDialog({
                title: 'Tell the ' + props.invited + ' invited?',
                text: event.title + ' is moving to ' + words(start, event.allDay) + '. Send the update to the ' + (props.invited === 1 ? 'person who has' : 'people who have') + ' it in their calendar?',
                showDenyButton: true,
                confirmButtonText: 'Move and send',
                denyButtonText: 'Just move it'
            }).then(function (result) {
                if (result.isConfirmed) {
                    send(true);
                } else if (result.isDenied) {
                    send(false);
                } else {
                    info.revert();
                }
            });
        } else {
            send(false);
        }
    }

    function booked(info) {
        const item = info.draggedEl;
        const start = new Date(info.date.getTime());
        if (info.allDay) {
            start.setHours(9, 0, 0, 0);
        }
        confirmDialog({
            title: 'Book ' + item.dataset.reference + ' for ' + words(start, false) + '?',
            text: 'The agent and the contact are emailed the time, as when it is scheduled on the ticket.',
            input: 'radio',
            inputOptions: {1: 'Onsite', 0: 'Remote'},
            inputValue: item.dataset.onsite === '1' ? '1' : '0',
            confirmButtonText: 'Book it'
        }).then(function (result) {
            if (!result.isConfirmed) {
                return;
            }
            post({kind: 'ticket', id: item.dataset.ticket, start: ymdhm(start), onsite: result.value === '0' ? 0 : 1}, function (json) {
                saved(json);
                item.remove();
                countUnscheduled();
                calendar.refetchEvents();
            }, function (error) {
                itflowToast(error, 'error');
            });
        });
    }

    // ---------- the sidebar ----------

    root.querySelectorAll('.itflow-cal-check').forEach(function (box) {
        box.addEventListener('change', function () {
            if (box.dataset.calendar) {
                state.hidden = state.hidden.filter(function (id) {
                    return id !== box.dataset.calendar;
                });
                if (!box.checked) {
                    state.hidden.push(box.dataset.calendar);
                }
            } else {
                state.sources[box.dataset.source] = box.checked;
            }
            save();
            calendar.refetchEvents();
        });
    });

    if (agentSelect) {
        agentSelect.addEventListener('change', function () {
            state.agent = agentId();
            save();
            calendar.refetchEvents();
            loadUnscheduled();
        });
    }

    const newButton = document.getElementById('calendarNewEvent');
    if (newButton) {
        newButton.addEventListener('click', function () {
            newEvent({});
        });
    }

    // ---------- To schedule ----------

    const priorityTones = {Urgent: 'danger', High: 'warning', Medium: 'primary', Low: 'secondary'};

    function countUnscheduled() {
        const count = document.getElementById('calendarUnscheduledCount');
        const hint = document.getElementById('calendarUnscheduledHint');
        const n = unscheduledList ? unscheduledList.children.length : 0;
        if (count) {
            count.textContent = n ? String(n) : '';
        }
        if (hint) {
            hint.textContent = n
                ? 'Open tickets with no time booked - drag one onto the calendar to book it.'
                : 'Nothing waiting - every open ticket ' + (agentId() && agentId() !== myId ? 'they have' : 'you have') + ' is booked or needs no visit.';
        }
    }

    function loadUnscheduled() {
        if (!unscheduledList) {
            return;
        }
        itflowGet('ajax.php', {calendar_unscheduled: 1, user_id: agentId() || myId, client_id: clientId}, function (text) {
            let tickets = [];
            try {
                tickets = JSON.parse(text).tickets || [];
            } catch (e) {
                tickets = [];
            }
            unscheduledList.innerHTML = '';
            tickets.forEach(function (ticket) {
                const item = document.createElement('div');
                item.className = 'itflow-cal-unscheduled';
                item.dataset.ticket = ticket.id;
                item.dataset.reference = ticket.reference;
                item.dataset.title = ticket.reference + ' ' + ticket.subject;
                item.dataset.onsite = ticket.onsite;
                item.title = 'Drag onto the calendar to book it - click for a quick look';

                const dot = document.createElement('span');
                dot.className = 'itflow-cal-priority bg-' + (priorityTones[ticket.priority] || 'secondary');
                dot.title = ticket.priority;
                item.appendChild(dot);

                const text = document.createElement('div');
                text.className = 'min-w-0';
                const line = document.createElement('div');
                line.className = 'text-truncate small fw-semibold';
                line.textContent = ticket.reference + ' ' + ticket.subject;
                const sub = document.createElement('div');
                sub.className = 'text-truncate small text-body-secondary';
                sub.textContent = (clientId ? '' : ticket.client + ' · ') + ticket.priority + (ticket.onsite ? ' · Onsite' : '');
                text.appendChild(line);
                text.appendChild(sub);
                item.appendChild(text);

                item.addEventListener('click', function () {
                    openModal('modals/ticket/ticket.php?id=' + ticket.id, 'lg');
                });
                unscheduledList.appendChild(item);
            });
            countUnscheduled();
        });
    }

    if (unscheduledList && canSchedule && FullCalendar.Interaction && FullCalendar.Interaction.Draggable) {
        new FullCalendar.Interaction.Draggable(unscheduledList, {
            itemSelector: '.itflow-cal-unscheduled',
            eventData: function (item) {
                return {title: item.dataset.title, duration: '01:00', create: false, color: '#0d6efd'};
            }
        });
        loadUnscheduled();
    }

})();
