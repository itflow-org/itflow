// Dispatch (agent/dispatch.php, functions/dispatch.php).
//
// The queue - tickets nobody has, with an agent and nothing booked, or left
// behind - beside the board: Day (a time grid per agent), Week (a row per agent,
// a column per day) or Workload (each agent's load). Dropping a ticket on an
// agent and a time, or moving a booking, opens the Schedule window and books it
// as the ticket's own Schedule window does (ajax.php?dispatch_book). A booking
// dragged back onto the queue is taken off the schedule. Under it, This week.
// Times come and go on the agent's own clock - the server moves them. The view,
// the agents shown and weekends are kept to the account (dispatch_state).

(function () {
    'use strict';

    const root = document.getElementById('itflowDispatch');
    if (!root || typeof FullCalendar === 'undefined') {
        return;
    }

    const csrf = root.dataset.csrf;
    const canEdit = root.dataset.canEdit === '1';
    const agents = JSON.parse(root.dataset.agents || '[]');
    const firstDay = Number(root.dataset.firstDay) || 0;
    const defaultMinutes = Number(root.dataset.defaultMinutes) || 60;
    const lengths = JSON.parse(root.dataset.lengths || '[]');
    const businessHours = JSON.parse(root.dataset.businessHours || 'null');
    const zone = root.dataset.zone || '';
    const queueEl = root.querySelector('[data-dispatch-queue]');
    const board = root.querySelector('[data-dispatch-board]');
    const statsEl = root.querySelector('[data-dispatch-stats]');
    const titleEl = root.querySelector('[data-dispatch-title]');
    const priorityTones = {Urgent: 'danger', High: 'warning', Medium: 'primary', Low: 'secondary'};

    const saved = JSON.parse(root.dataset.state || '{}');
    const state = {
        view: saved.view || 'day',
        hidden: Array.isArray(saved.hidden) ? saved.hidden.map(Number) : [],
        weekends: !!saved.weekends,
        date: parseYmd(root.dataset.initialDate),
        tab: 'unassigned',
        filters: {}
    };
    let calendars = [];
    let sortables = [];
    let queueDraggable = null;
    let queueTickets = [];
    let lastItems = [];
    const expanded = {};

    // ---------- helpers ----------

    function pad(n) {
        return String(n).padStart(2, '0');
    }
    function ymd(date) {
        return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
    }
    function hm(date) {
        return pad(date.getHours()) + ':' + pad(date.getMinutes());
    }
    function parseYmd(text) {
        const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(text || '');
        return m ? new Date(+m[1], +m[2] - 1, +m[3]) : new Date();
    }
    function parseDateTime(text) {
        const m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/.exec(String(text || ''));
        return m ? new Date(+m[1], +m[2] - 1, +m[3], +m[4], +m[5]) : null;
    }
    function addDays(date, n) {
        const d = new Date(date.getTime());
        d.setDate(d.getDate() + n);
        return d;
    }
    function weekStart(date) {
        return addDays(date, -((date.getDay() - firstDay + 7) % 7));
    }
    function esc(text) {
        const div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }
    function lengthText(minutes) {
        const h = Math.floor(minutes / 60);
        const m = minutes % 60;
        return ((h ? h + (h > 1 ? ' hours' : ' hour') : '') + (m ? ' ' + m + ' min' : '')).trim();
    }
    function minutesBetween(start, end) {
        const a = parseDateTime(start);
        const b = parseDateTime(end);
        return a && b ? Math.max(0, Math.round((b - a) / 60000)) : 0;
    }
    function toast(message, type) {
        if (typeof itflowToast === 'function') {
            itflowToast(message, type || 'success');
        }
    }
    // Now on the agent's own clock, when they work away from the company's
    function zoneNow() {
        const parts = {};
        new Intl.DateTimeFormat('en-CA', {timeZone: zone, hourCycle: 'h23', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit'})
            .formatToParts(new Date()).forEach(function (part) {
                parts[part.type] = part.value;
            });
        return parts.year + '-' + parts.month + '-' + parts.day + 'T' + parts.hour + ':' + parts.minute + ':' + parts.second;
    }
    function today() {
        return zone ? parseYmd(zoneNow()) : new Date();
    }

    function request(method, params) {
        const query = new URLSearchParams(params).toString();
        const options = {method: method, credentials: 'same-origin'};
        if (method === 'POST') {
            options.headers = {'Content-Type': 'application/x-www-form-urlencoded'};
            options.body = query;
        }
        return fetch('ajax.php' + (method === 'GET' ? '?' + query : ''), options).then(function (res) {
            return res.json().catch(function () {
                return {error: 'Something went wrong - reload the page and try again.'};
            });
        }).then(function (json) {
            if (json && json.error) {
                throw new Error(json.error);
            }
            return json;
        });
    }
    function post(params) {
        return request('POST', Object.assign({csrf_token: csrf}, params));
    }

    let saveTimer = null;
    function saveState() {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(function () {
            post({dispatch_state: 1, view: state.view, hidden: state.hidden.join(','), weekends: state.weekends ? 1 : 0}).catch(function () {
                // Not kept this time - the page still shows it
            });
        }, 600);
    }

    function agentById(id) {
        return agents.find(function (a) {
            return a.id === Number(id);
        });
    }
    function shownAgents() {
        return agents.filter(function (a) {
            return state.hidden.indexOf(a.id) === -1;
        });
    }
    function dot(color) {
        return '<span class="itflow-dispatch-dot me-1" style="background: ' + esc(color) + '"></span>';
    }
    function businessStart() {
        return businessHours && businessHours.startTime && businessHours.startTime !== '00:00' ? businessHours.startTime : '09:00';
    }

    // ---------- asking: the Schedule window, and taking a booking off ----------

    const scheduleModalEl = document.getElementById('dispatchScheduleModal');
    const scheduleForm = scheduleModalEl.querySelector('[data-dispatch-schedule]');
    const scheduleError = scheduleModalEl.querySelector('[data-dispatch-schedule-error]');
    let scheduling = null;

    // Book a ticket: [ticket] from the queue or the board, at [start] (a Date), for [agentId] - resolves true once booked
    function schedule(ticket, start, agentId, minutes) {
        if (!canEdit) {
            return Promise.resolve(false);
        }
        return new Promise(function (resolve) {
            scheduling = {ticket: ticket, resolve: resolve, done: false};
            scheduleModalEl.querySelector('[data-dispatch-schedule-ref]').textContent = ticket.reference || '';
            scheduleModalEl.querySelector('[data-dispatch-schedule-ticket]').innerHTML =
                '<a class="fw-semibold" href="ticket.php?ticket_id=' + Number(ticket.id) + '" target="_blank">' + esc(ticket.reference) + ' ' + esc(ticket.subject) + '</a>'
                + '<div class="text-body-secondary">' + esc([ticket.client, ticket.priority, ticket.status, ticket.booked ? 'booked ' + ticket.booked : ''].filter(Boolean).join(' · ')) + '</div>';
            const length = minutes || ticket.minutes || defaultMinutes;
            const list = lengths.indexOf(length) === -1 ? lengths.concat([length]).sort(function (a, b) { return a - b; }) : lengths;
            scheduleForm.elements.minutes.innerHTML = list.map(function (m) {
                return '<option value="' + m + '"' + (m === length ? ' selected' : '') + '>' + esc(lengthText(m)) + '</option>';
            }).join('');
            scheduleForm.elements.minutes.value = String(length);
            scheduleForm.elements.agent_id.value = String(agentId || ticket.agent || (agents[0] || {}).id || '');
            scheduleForm.elements.start.value = ymd(start) + 'T' + hm(start);
            scheduleForm.elements.onsite.value = ticket.onsite ? '1' : '0';
            scheduleError.hidden = true;
            bootstrap.Modal.getOrCreateInstance(scheduleModalEl).show();
        });
    }

    scheduleForm.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!scheduling) {
            return;
        }
        const button = scheduleForm.querySelector('[type=submit]');
        button.disabled = true;
        const tell = scheduleForm.elements.tell_client;
        post({
            dispatch_book: 1,
            ticket_id: scheduling.ticket.id,
            agent_id: scheduleForm.elements.agent_id.value,
            start: scheduleForm.elements.start.value.replace('T', ' ') + ':00',
            minutes: scheduleForm.elements.minutes.value,
            onsite: scheduleForm.elements.onsite.value,
            tell_client: tell ? (tell.checked ? 1 : 0) : 1
        }).then(function (json) {
            scheduling.done = true;
            toast(json.message, 'success');
            if (json.conflicts && json.conflicts.length) {
                toast('Already booked then: ' + json.conflicts.join('; '), 'warning');
            }
            bootstrap.Modal.getOrCreateInstance(scheduleModalEl).hide();
            reloadAll();
        }).catch(function (error) {
            scheduleError.textContent = error.message;
            scheduleError.hidden = false;
        }).finally(function () {
            button.disabled = false;
        });
    });

    scheduleModalEl.addEventListener('hidden.bs.modal', function () {
        if (scheduling) {
            const done = scheduling.done;
            scheduling.resolve(done);
            scheduling = null;
            if (!done) {
                refreshBoard(); // anything dragged goes back where it was
            }
        }
    });

    const confirmEl = document.getElementById('dispatchConfirmModal');
    let confirming = null;
    function confirmBox(title, text) {
        return new Promise(function (resolve) {
            confirming = {resolve: resolve, ok: false};
            confirmEl.querySelector('[data-dispatch-confirm-title]').textContent = title;
            confirmEl.querySelector('[data-dispatch-confirm-text]').textContent = text;
            bootstrap.Modal.getOrCreateInstance(confirmEl).show();
        });
    }
    confirmEl.querySelector('[data-dispatch-confirm-ok]').addEventListener('click', function () {
        if (confirming) {
            confirming.ok = true;
        }
        bootstrap.Modal.getOrCreateInstance(confirmEl).hide();
    });
    confirmEl.addEventListener('hidden.bs.modal', function () {
        if (confirming) {
            confirming.resolve(confirming.ok);
            confirming = null;
        }
    });

    function unbook(ticket) {
        if (!canEdit) {
            return;
        }
        confirmBox('Take ' + (ticket.reference || 'it') + ' off the schedule?', 'The agent - and the contact and watchers, when client emails are on - are sent the cancellation. It goes back to the queue.').then(function (ok) {
            if (!ok) {
                refreshBoard();
                return;
            }
            post({dispatch_unbook: 1, ticket_id: ticket.id}).then(function (json) {
                toast(json.message, 'success');
                reloadAll();
            }).catch(function (error) {
                toast(error.message, 'error');
                refreshBoard();
            });
        });
    }

    function moveEvent(props, start, end, allDay, from, to) {
        post({
            dispatch_event_move: 1, event_id: props.event_id, from: from, to: to, all_day: allDay ? 1 : 0,
            start: ymd(start) + ' ' + (allDay ? '00:00' : hm(start)) + ':00',
            end: end && !allDay ? ymd(end) + ' ' + hm(end) + ':00' : ''
        }).then(function (json) {
            toast(json.message, 'success');
            refreshBoard();
        }).catch(function (error) {
            toast(error.message, 'error');
            refreshBoard();
        });
    }

    // ---------- the queue ----------

    function ticketFromQueue(el) {
        return queueTickets.find(function (t) {
            return t.id === Number(el.dataset.ticket);
        }) || {id: Number(el.dataset.ticket), reference: el.dataset.reference || ''};
    }
    function ticketFromItem(event, props) {
        return {
            id: Number(props.ticket_id),
            reference: props.reference || '',
            subject: event.title,
            agent: Number(props.agent),
            onsite: props.icon === 'fa-car' ? 1 : 0,
            minutes: event.end && event.start ? Math.round((event.end - event.start) / 60000) : defaultMinutes
        };
    }

    function loadQueue() {
        request('GET', Object.assign({dispatch_queue: state.tab}, state.filters)).then(function (json) {
            queueTickets = json.tickets || [];
            Object.keys(json.counts || {}).forEach(function (key) {
                const badge = root.querySelector('[data-dispatch-count="' + key + '"]');
                if (badge) {
                    badge.textContent = json.counts[key] ? String(json.counts[key]) : '';
                }
            });
            root.querySelectorAll('[data-dispatch-tab]').forEach(function (tab) {
                tab.classList.toggle('active', tab.dataset.dispatchTab === state.tab);
            });
            if (!queueTickets.length) {
                queueEl.innerHTML = '<div class="text-body-secondary small p-2">' + esc({
                    unassigned: 'Every open ticket has an agent.',
                    unbooked: 'Nothing waiting - every ticket with an agent is booked.',
                    left_behind: 'Nothing left behind - every open ticket booked before today has been moved on or done.'
                }[state.tab]) + '</div>';
                return;
            }
            queueEl.innerHTML = queueTickets.map(function (t) {
                return '<div class="itflow-dispatch-item' + (canEdit ? ' is-movable' : '') + '" data-ticket="' + t.id + '" data-reference="' + esc(t.reference) + '" data-priority="' + esc(t.priority) + '" title="' + esc(canEdit ? 'Drag onto an agent and a time - click for a quick look' : 'Click for a quick look') + '">'
                    + '<div class="d-flex align-items-center gap-2"><span class="itflow-cal-priority bg-' + (priorityTones[t.priority] || 'secondary') + '" title="' + esc(t.priority) + '"></span>'
                    + '<span class="small fw-semibold text-truncate">' + esc(t.reference + ' ' + t.subject) + '</span></div>'
                    + '<div class="small text-body-secondary text-truncate">' + esc([t.client, t.agent_name, t.booked ? 'booked ' + t.booked : t.age, t.onsite ? 'Onsite' : ''].filter(Boolean).join(' · ')) + '</div></div>';
            }).join('');
        }).catch(function (error) {
            queueEl.innerHTML = '<div class="text-danger small p-2">' + esc(error.message) + '</div>';
        });
    }

    queueEl.addEventListener('click', function (e) {
        const item = e.target.closest('.itflow-dispatch-item');
        if (item) {
            openModal('modals/ticket/ticket.php?id=' + Number(item.dataset.ticket));
        }
    });
    root.querySelectorAll('[data-dispatch-tab]').forEach(function (tab) {
        tab.addEventListener('click', function (e) {
            e.preventDefault();
            state.tab = tab.dataset.dispatchTab;
            loadQueue();
        });
    });
    let filterTimer = null;
    root.querySelectorAll('[data-dispatch-filter]').forEach(function (input) {
        input.addEventListener(input.tagName === 'SELECT' ? 'change' : 'input', function () {
            state.filters[input.dataset.dispatchFilter] = input.value;
            clearTimeout(filterTimer);
            filterTimer = setTimeout(loadQueue, input.tagName === 'SELECT' ? 0 : 300);
        });
    });

    function openModal(url) {
        const link = document.createElement('a');
        link.href = '#';
        link.className = 'ajax-modal d-none';
        link.dataset.modalUrl = url;
        link.dataset.modalSize = 'lg';
        document.body.appendChild(link);
        link.click();
        link.remove();
    }

    function overQueue(jsEvent) {
        const rect = queueEl.getBoundingClientRect();
        return jsEvent && jsEvent.clientX >= rect.left && jsEvent.clientX <= rect.right && jsEvent.clientY >= rect.top && jsEvent.clientY <= rect.bottom;
    }

    // ---------- the board ----------

    function range() {
        if (state.view === 'week') {
            const start = weekStart(state.date);
            return [start, addDays(start, 6)];
        }
        return [state.date, state.date];
    }

    function loadItems() {
        const r = range();
        return request('GET', {dispatch_items: 1, from: ymd(r[0]), to: ymd(r[1]), agents: shownAgents().map(function (a) {
            return a.id;
        }).join(',')}).then(function (items) {
            lastItems = Array.isArray(items) ? items : [];
            return lastItems;
        });
    }

    function teardown() {
        calendars.forEach(function (cal) {
            cal.destroy();
        });
        calendars = [];
        sortables.forEach(function (s) {
            s.destroy();
        });
        sortables = [];
        if (queueDraggable) {
            queueDraggable.destroy();
            queueDraggable = null;
        }
    }

    function render() {
        teardown();
        root.querySelectorAll('[data-dispatch-view]').forEach(function (b) {
            b.classList.toggle('active', b.dataset.dispatchView === state.view);
        });
        root.querySelectorAll('[data-dispatch-nav], [data-dispatch-date]').forEach(function (el) {
            el.disabled = state.view === 'workload';
        });
        root.querySelector('[data-dispatch-date]').value = ymd(state.date);
        root.querySelector('[data-dispatch-agent-count]').textContent = '(' + shownAgents().length + '/' + agents.length + ')';
        const r = range();
        titleEl.textContent = state.view === 'workload' ? 'Workload - open tickets and the hours booked this week'
            : (state.view === 'week'
                ? r[0].toLocaleDateString(undefined, {month: 'short', day: 'numeric'}) + ' - ' + r[1].toLocaleDateString(undefined, {month: 'short', day: 'numeric', year: 'numeric'})
                : state.date.toLocaleDateString(undefined, {weekday: 'long', month: 'long', day: 'numeric', year: 'numeric'}));
        if (!shownAgents().length) {
            board.innerHTML = '<div class="p-4 text-body-secondary">Pick the agents to show under Agents.</div>';
            return;
        }
        if (state.view === 'day') {
            renderDay();
        } else if (state.view === 'week') {
            renderWeek();
        } else {
            renderWorkload();
        }
    }

    // [scroll] opens the day at its first hour - a new day; a refresh keeps the place
    function refreshBoard(scroll) {
        if (state.view === 'day') {
            loadItems().then(function (items) {
                calendars.forEach(function (cal) {
                    cal.removeAllEventSources();
                    cal.addEventSource(items.filter(function (item) {
                        return Number(item.extendedProps.agent) === cal.dispatchAgent;
                    }));
                });
                dayTotals(items);
                if (scroll === true) {
                    requestAnimationFrame(scrollDay);
                }
            });
        } else {
            render();
        }
    }

    function reloadAll() {
        loadQueue();
        refreshBoard();
        loadStats();
    }

    // An item drawn as on the calendar page: time, icon, title, and where / who
    function itemContent(arg) {
        const props = arg.event.extendedProps || {};
        const wrap = document.createElement('div');
        wrap.className = 'itflow-cal-event ' + (arg.event.allDay ? 'is-daygrid' : 'is-timegrid');
        if (arg.timeText && !arg.event.allDay) {
            const time = document.createElement('span');
            time.className = 'itflow-cal-time';
            time.textContent = arg.timeText;
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
        if (props.detail && !arg.event.allDay) {
            const detail = document.createElement('div');
            detail.className = 'itflow-cal-detail';
            detail.textContent = props.detail;
            wrap.appendChild(detail);
        }
        return {domNodes: [wrap]};
    }

    // The hours the day's grid shows - the working day, with room either side
    function slotRange() {
        if (zone || !businessHours || businessHours.startTime === '00:00') {
            return ['00:00:00', '24:00:00'];
        }
        const start = businessHours.startTime < '06:00' ? businessHours.startTime : '06:00';
        const end = businessHours.endTime > '21:00' ? businessHours.endTime : '21:00';
        return [start + ':00', end === '24:00' ? '24:00:00' : end + ':00'];
    }

    // Day: a time grid for each agent, side by side
    function renderDay() {
        const shown = shownAgents();
        board.innerHTML = '<div class="itflow-dispatch-cols">' + shown.map(function (a) {
            return '<div class="itflow-dispatch-col" data-agent="' + a.id + '"><div class="itflow-dispatch-col-head small fw-semibold text-truncate">'
                + dot(a.color) + esc(a.name) + ' <span class="fw-normal text-body-secondary ms-1" data-dispatch-booked></span></div><div class="itflow-dispatch-cal"><div class="h-100" data-dispatch-cal></div></div></div>';
        }).join('') + '</div>';
        const slots = slotRange();

        board.querySelectorAll('.itflow-dispatch-col').forEach(function (col, index) {
            const agentId = Number(col.dataset.agent);
            const cal = new FullCalendar.Calendar(col.querySelector('[data-dispatch-cal]'), {
                initialView: 'timeGridDay',
                initialDate: ymd(state.date),
                headerToolbar: false,
                dayHeaders: false,
                allDaySlot: true,
                allDayText: '',
                height: 'auto',
                slotMinTime: slots[0],
                slotMaxTime: slots[1],
                now: zone ? zoneNow : undefined,
                slotDuration: '00:30:00',
                snapDuration: '00:15:00',
                slotEventOverlap: false,
                nowIndicator: true,
                editable: canEdit,
                droppable: canEdit,
                eventDurationEditable: canEdit,
                // The company's hours mean nothing on another clock - left unshaded there
                businessHours: zone ? false : (businessHours || false),
                // The times down the side once, on the first column - the others keep a blank the
                // label's height, so the rows line up across
                slotHeaderContent: function (arg) {
                    return index === 0 ? arg.text : '\u00a0';
                },
                eventContent: itemContent,
                eventDidMount: function (info) {
                    const props = info.event.extendedProps || {};
                    info.el.dataset.kind = props.kind || '';
                    info.el.setAttribute('title', info.event.title + (props.detail ? '\n' + props.detail : ''));
                },
                eventClick: function (info) {
                    info.jsEvent.preventDefault();
                    const props = info.event.extendedProps || {};
                    if (props.modal) {
                        openModal(props.modal);
                    }
                },
                // From the queue - a booking from another agent's column comes in by eventReceive
                drop: function (info) {
                    if (!queueEl.contains(info.draggedEl)) {
                        return;
                    }
                    const start = new Date(info.date.getTime());
                    if (info.allDay) {
                        const parts = businessStart().split(':');
                        start.setHours(Number(parts[0]), Number(parts[1]), 0, 0);
                    }
                    schedule(ticketFromQueue(info.draggedEl), start, agentId);
                },
                // To another time, the same agent
                eventDrop: function (info) {
                    const props = info.event.extendedProps || {};
                    if (props.kind === 'event') {
                        moveEvent(props, info.event.start, info.event.end, info.event.allDay, agentId, agentId);
                        return;
                    }
                    schedule(ticketFromItem(info.event, props), info.event.start, agentId);
                },
                eventResize: function (info) {
                    const props = info.event.extendedProps || {};
                    if (props.kind === 'event') {
                        moveEvent(props, info.event.start, info.event.end, false, agentId, agentId);
                        return;
                    }
                    const ticket = ticketFromItem(info.event, props);
                    schedule(ticket, info.event.start, agentId, ticket.minutes);
                },
                // From another agent's column
                eventReceive: function (info) {
                    const props = info.event.extendedProps || {};
                    if (props.kind === 'event') {
                        moveEvent(props, info.event.start, info.event.end, info.event.allDay, Number(props.agent), agentId);
                        return;
                    }
                    schedule(ticketFromItem(info.event, props), info.event.start, agentId);
                },
                // Back onto the queue
                eventDragStart: function () {
                    queueEl.classList.add('is-drop-target');
                },
                eventDragStop: function (info) {
                    queueEl.classList.remove('is-drop-target');
                    const props = info.event.extendedProps || {};
                    if (props.kind === 'ticket' && overQueue(info.jsEvent)) {
                        unbook(ticketFromItem(info.event, props));
                    }
                }
            });
            cal.dispatchAgent = agentId;
            cal.render();
            calendars.push(cal);
        });

        if (canEdit && FullCalendar.Interaction && FullCalendar.Interaction.Draggable) {
            queueDraggable = new FullCalendar.Interaction.Draggable(queueEl, {
                itemSelector: '.itflow-dispatch-item',
                eventData: function (item) {
                    const minutes = ticketFromQueue(item).minutes || defaultMinutes;
                    return {title: item.dataset.reference, duration: pad(Math.floor(minutes / 60)) + ':' + pad(minutes % 60), create: false, color: '#6c757d'};
                }
            });
        }
        refreshBoard(true);
    }

    // The hour to open the day at: the working day's start - or, when the board runs around the clock,
    // an hour before now today and 8am on other days
    function scrollHour() {
        if (slotRange()[0] !== '00:00:00') {
            return Number(businessStart().split(':')[0]);
        }
        if (ymd(state.date) === ymd(today())) {
            return Math.max(0, (zone ? parseDateTime(zoneNow()) : new Date()).getHours() - 1);
        }
        return 8;
    }

    // One scroller for every column - opened at scrollHour(), once the bookings are in
    function scrollDay() {
        const scroller = board.querySelector('.itflow-dispatch-cols');
        const slot = scroller ? scroller.querySelector('.itflow-dispatch-col [data-time="' + pad(scrollHour()) + ':00:00"]') : null;
        if (slot) {
            const head = scroller.querySelector('.itflow-dispatch-col-head');
            scroller.scrollTop += slot.getBoundingClientRect().top - scroller.getBoundingClientRect().top - (head ? head.offsetHeight : 0) - 4;
        }
    }

    function dayTotals(items) {
        board.querySelectorAll('.itflow-dispatch-col').forEach(function (col) {
            let minutes = 0;
            items.forEach(function (item) {
                if (Number(item.extendedProps.agent) === Number(col.dataset.agent) && ['ticket', 'helper'].indexOf(item.extendedProps.kind) !== -1 && !item.allDay) {
                    minutes += minutesBetween(item.start, item.end);
                }
            });
            col.querySelector('[data-dispatch-booked]').textContent = minutes ? lengthText(minutes) + ' booked' : '';
        });
    }

    // Week: a row for each agent, a column for each day
    function renderWeek() {
        const start = range()[0];
        const days = [];
        for (let i = 0; i < 7; i++) {
            const d = addDays(start, i);
            if (state.weekends || (d.getDay() !== 0 && d.getDay() !== 6)) {
                days.push(d);
            }
        }
        board.innerHTML = '<div class="p-3 text-body-secondary small">Loading...</div>';
        loadItems().then(function (items) {
            const now = ymd(today());
            let html = '<div class="table-responsive itflow-dispatch-week-wrap"><table class="table table-bordered table-sm mb-0 itflow-dispatch-week"><thead><tr><th class="itflow-dispatch-week-agent">Agent</th>';
            days.forEach(function (d) {
                html += '<th class="small text-center' + (ymd(d) === now ? ' text-primary' : '') + '">' + esc(d.toLocaleDateString(undefined, {weekday: 'short', month: 'short', day: 'numeric'})) + '</th>';
            });
            html += '</tr></thead><tbody>';
            shownAgents().forEach(function (a) {
                // The week's booked hours - weekends too, shown or not, as Workload counts them
                let week = 0;
                items.forEach(function (item) {
                    if (Number(item.extendedProps.agent) === a.id && ['ticket', 'helper'].indexOf(item.extendedProps.kind) !== -1 && !item.allDay) {
                        week += minutesBetween(item.start, item.end);
                    }
                });
                const cells = days.map(function (d) {
                    const day = ymd(d);
                    const chips = items.filter(function (item) {
                        return Number(item.extendedProps.agent) === a.id && String(item.start).substring(0, 10) === day;
                    }).sort(function (x, y) {
                        return String(x.start).localeCompare(String(y.start));
                    });
                    const off = businessHours && businessHours.daysOfWeek && businessHours.daysOfWeek.indexOf(d.getDay()) === -1;
                    // A box of a fixed height - one that grew as a booking passed over would move the rows under the pointer
                    return '<td class="itflow-dispatch-cell' + (off ? ' is-off' : '') + '"><div class="itflow-dispatch-cell-list" data-agent="' + a.id + '" data-date="' + day + '">' + chips.map(function (item) {
                        const props = item.extendedProps || {};
                        const minutes = item.allDay ? 0 : minutesBetween(item.start, item.end);
                        const movable = !!item.editable;
                        return '<div class="itflow-dispatch-chip' + (movable ? ' is-movable' : '') + '" style="background-color: ' + esc(item.color || '#6c757d') + '" data-kind="' + esc(props.kind || '') + '"'
                            + ' data-ticket="' + Number(props.ticket_id || 0) + '" data-event="' + Number(props.event_id || 0) + '" data-reference="' + esc(props.reference || '') + '"'
                            + ' data-start="' + esc(String(item.start).substring(0, 16)) + '" data-end="' + esc(String(item.end || '').substring(0, 16)) + '" data-all-day="' + (item.allDay ? 1 : 0) + '"'
                            + ' data-minutes="' + minutes + '" data-onsite="' + (props.icon === 'fa-car' ? 1 : 0) + '" data-modal="' + esc(props.modal || '') + '"'
                            + ' title="' + esc(item.title + (props.detail ? '\n' + props.detail : '')) + '">'
                            + (props.icon ? '<i class="fas fa-fw ' + esc(props.icon) + ' me-1" aria-hidden="true"></i>' : '')
                            + esc((item.allDay ? '' : String(item.start).substring(11, 16) + ' ') + item.title) + '</div>';
                    }).join('') + '</div></td>';
                }).join('');
                html += '<tr><th class="small itflow-dispatch-week-agent">' + dot(a.color) + esc(a.name)
                    + '<div class="fw-normal text-body-secondary">' + (week ? esc(lengthText(week)) + ' booked' : '') + '</div></th>' + cells + '</tr>';
            });
            board.innerHTML = html + '</tbody></table></div>';

            board.querySelectorAll('.itflow-dispatch-chip').forEach(function (chip) {
                chip.addEventListener('click', function () {
                    if (chip.dataset.modal) {
                        openModal(chip.dataset.modal);
                    }
                });
            });
            if (!canEdit || typeof Sortable === 'undefined') {
                return;
            }
            board.querySelectorAll('.itflow-dispatch-cell-list').forEach(function (cell) {
                sortables.push(Sortable.create(cell, {
                    group: {name: 'dispatch', pull: true, put: true},
                    draggable: '.is-movable',
                    sort: false,
                    onAdd: function (evt) {
                        const el = evt.item;
                        const agentId = Number(cell.dataset.agent);
                        const day = parseYmd(cell.dataset.date);
                        const from = evt.from;
                        el.remove();
                        if (from === queueEl) {
                            // From the queue: the start of the working day
                            const parts = businessStart().split(':');
                            day.setHours(Number(parts[0]), Number(parts[1]), 0, 0);
                            schedule(ticketFromQueue(el), day, agentId);
                            return;
                        }
                        const was = parseDateTime(el.dataset.start) || day;
                        day.setHours(was.getHours(), was.getMinutes(), 0, 0);
                        const fromAgent = Number(from.dataset.agent);
                        if (el.dataset.kind === 'event') {
                            const length = Number(el.dataset.minutes) * 60000;
                            moveEvent({event_id: Number(el.dataset.event)}, day, length ? new Date(day.getTime() + length) : null, el.dataset.allDay === '1', fromAgent, agentId);
                            return;
                        }
                        schedule({id: Number(el.dataset.ticket), reference: el.dataset.reference, subject: el.title.split('\n')[0], onsite: Number(el.dataset.onsite), agent: fromAgent},
                            day, agentId, Number(el.dataset.minutes) || defaultMinutes);
                    }
                }));
            });
            // The queue takes from the board (to take a booking off) and gives to it
            sortables.push(Sortable.create(queueEl, {
                group: {name: 'dispatch', pull: 'clone', put: true},
                draggable: '.itflow-dispatch-item.is-movable, .itflow-dispatch-chip',
                sort: false,
                onAdd: function (evt) {
                    const el = evt.item;
                    el.remove();
                    if (el.dataset.kind === 'ticket') {
                        unbook({id: Number(el.dataset.ticket), reference: el.dataset.reference});
                    } else {
                        refreshBoard();
                    }
                }
            }));
        }).catch(function (error) {
            board.innerHTML = '<div class="p-3 text-danger small">' + esc(error.message) + '</div>';
        });
    }

    // Workload: each agent's open tickets, SLAs and booked hours - drop a ticket on an agent to give it to them
    function bar(minutes, capacity) {
        const pct = capacity ? Math.min(100, Math.round(minutes / capacity * 100)) : 0;
        const tone = capacity && minutes > capacity ? 'bg-danger' : (pct >= 85 ? 'bg-warning' : 'bg-primary');
        return '<div class="small text-nowrap">' + esc(lengthText(minutes) || '0 min') + (capacity ? ' <span class="text-body-secondary">of ' + esc(lengthText(capacity)) + '</span>' : '') + '</div>'
            + '<div class="progress itflow-dispatch-bar" role="progressbar" aria-valuenow="' + pct + '" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar ' + tone + '" style="width: ' + pct + '%"></div></div>';
    }

    function renderWorkload() {
        board.innerHTML = '<div class="p-3 text-body-secondary small">Loading...</div>';
        request('GET', {dispatch_workload: 1, agents: shownAgents().map(function (a) { return a.id; }).join(','), first_day: firstDay}).then(function (json) {
            const flagTones = {behind: 'danger', watch: 'warning', overloaded: 'warning', room: 'success'};
            let html = '<div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0 itflow-dispatch-workload"><thead class="text-nowrap"><tr>'
                + '<th>Agent</th><th class="text-end">Open</th><th class="text-end" title="SLA breached / close to it">SLA</th><th class="text-end">Past due</th><th class="text-end" title="Leading, with nothing booked">Not booked</th><th class="text-end" title="No reply from us for three days or more, and not waiting on the client">Quiet</th><th>Booked today</th><th>Booked this week</th></tr></thead><tbody>';
            (json.agents || []).forEach(function (a) {
                html += '<tr class="itflow-dispatch-workload-row" data-agent="' + a.id + '">'
                    + '<td><div class="itflow-dispatch-drop" data-agent="' + a.id + '">' + dot(a.color) + '<span class="fw-semibold">' + esc(a.name) + '</span> '
                    + (a.flags || []).map(function (f) {
                        return '<span class="badge rounded-pill text-bg-' + (flagTones[f.key] || 'secondary') + ' ms-1" title="' + esc(f.why) + '">' + esc(f.text) + '</span>';
                    }).join('') + (a.oldest ? '<div class="small text-body-secondary">Oldest ' + esc(a.oldest) + '</div>' : '') + '</div></td>'
                    + '<td class="text-end">' + a.open + (a.helping ? ' <span class="small text-body-secondary" title="Helping on">(' + a.helping + ' helping)</span>' : '') + '</td>'
                    + '<td class="text-end text-nowrap">' + (a.sla.breached ? '<span class="text-danger fw-semibold">' + a.sla.breached + '</span>' : '0') + ' / ' + (a.sla.at_risk ? '<span class="text-warning-emphasis fw-semibold">' + a.sla.at_risk + '</span>' : '0') + '</td>'
                    + '<td class="text-end">' + (a.overdue ? '<span class="text-danger fw-semibold">' + a.overdue + '</span>' : '0') + '</td>'
                    + '<td class="text-end">' + a.unbooked + '</td>'
                    + '<td class="text-end">' + a.quiet + '</td>'
                    + '<td>' + bar(a.booked_today, a.capacity_today) + '</td>'
                    + '<td>' + bar(a.booked_week, a.capacity_week) + '</td></tr>';
                html += '<tr class="itflow-dispatch-workload-tickets"' + (expanded[a.id] ? '' : ' hidden') + ' data-tickets-for="' + a.id + '"><td colspan="8" class="bg-body-tertiary">'
                    + (a.tickets.length ? '<div class="list-group list-group-flush">' + a.tickets.map(function (t) {
                        return '<a class="list-group-item list-group-item-action bg-transparent small d-flex flex-wrap align-items-center gap-2" href="' + esc(t.url) + '">'
                            + '<span class="itflow-cal-priority bg-' + (priorityTones[t.priority] || 'secondary') + '"></span>'
                            + '<span class="fw-semibold">' + esc(t.reference) + '</span><span class="text-truncate">' + esc(t.subject) + '</span>'
                            + '<span class="text-body-secondary">' + esc(t.client) + '</span>'
                            + (t.role === 'helper' ? '<span class="badge rounded-pill bg-body-secondary text-body-secondary border">Helping</span>' : '')
                            + t.sla_html + t.due_html
                            + '<span class="ms-auto text-body-secondary">' + esc(t.scheduled ? 'Booked ' + t.scheduled : 'Not booked') + (t.quiet ? ' · quiet since ' + esc(t.last) : '') + '</span></a>';
                    }).join('') + '</div>' : '<div class="small text-body-secondary p-2">No open tickets.</div>') + '</td></tr>';
            });
            html += '</tbody></table></div><div class="small text-body-secondary p-2 border-top">Team average: ' + esc(json.team.open) + ' open, ' + esc(lengthText(json.team.booked_week) || '0 min') + ' booked this week. Click an agent for their tickets'
                + (canEdit ? ' - drop a ticket from the queue on one to give it to them.' : '.') + '</div>';
            board.innerHTML = html;

            board.querySelectorAll('.itflow-dispatch-workload-row').forEach(function (row) {
                row.addEventListener('click', function () {
                    const id = Number(row.dataset.agent);
                    expanded[id] = !expanded[id];
                    board.querySelector('[data-tickets-for="' + id + '"]').hidden = !expanded[id];
                });
            });
            if (!canEdit || typeof Sortable === 'undefined') {
                return;
            }
            board.querySelectorAll('.itflow-dispatch-drop').forEach(function (drop) {
                sortables.push(Sortable.create(drop, {
                    group: {name: 'dispatch', pull: false, put: true},
                    sort: false,
                    onAdd: function (evt) {
                        const ticket = ticketFromQueue(evt.item);
                        evt.item.remove();
                        post({dispatch_assign: 1, ticket_id: ticket.id, agent_id: drop.dataset.agent}).then(function (json) {
                            toast(json.message, 'success');
                            reloadAll();
                        }).catch(function (error) {
                            toast(error.message, 'error');
                        });
                    }
                }));
            });
            sortables.push(Sortable.create(queueEl, {
                group: {name: 'dispatch', pull: 'clone', put: false},
                draggable: '.itflow-dispatch-item.is-movable',
                sort: false,
                onStart: function () {
                    board.classList.add('is-dragging');
                },
                onEnd: function () {
                    board.classList.remove('is-dragging');
                }
            }));
        }).catch(function (error) {
            board.innerHTML = '<div class="p-3 text-danger small">' + esc(error.message) + '</div>';
        });
    }

    // ---------- This week ----------

    let statsChart = null;
    function loadStats() {
        request('GET', {dispatch_week: 1, agents: shownAgents().map(function (a) { return a.id; }).join(','), first_day: firstDay}).then(function (json) {
            const t = json.totals;
            const s = json.sla;
            const from = parseYmd(json.week[0]);
            const to = parseYmd(json.week[1]);
            root.querySelector('[data-dispatch-week]').textContent = from.toLocaleDateString(undefined, {month: 'short', day: 'numeric'}) + ' - ' + to.toLocaleDateString(undefined, {month: 'short', day: 'numeric'});
            const tile = function (label, value, sub) {
                return '<div class="itflow-dispatch-tile"><div class="itflow-dispatch-tile-label">' + esc(label) + '</div><div class="itflow-dispatch-tile-value">' + esc(value) + '</div><div class="itflow-dispatch-tile-sub">' + sub + '</div></div>';
            };
            const signed = function (n) {
                return (n > 0 ? '+' : '') + n;
            };
            let html = '<div class="itflow-dispatch-tiles mb-3">'
                + tile('In', t.inbound, esc('Last week ' + t.last_inbound))
                + tile('Closed', t.closed, esc('Last week ' + t.last_closed + ' · 4-week average ' + t.avg_closed))
                + tile('In minus closed', signed(t.net), esc(t.net > 0 ? 'The queue grew' : (t.net < 0 ? 'The queue shrank' : 'Even')))
                + tile('Open now', t.open, esc((s.breached_now || 0) + ' SLA breached · ' + (s.at_risk_now || 0) + ' close to it'))
                + tile('First responses', (s.response_met || 0) + ' met', esc((s.response_missed || 0) + ' missed this week'))
                + tile('Resolutions', (s.resolution_met || 0) + ' met', esc((s.resolution_missed || 0) + ' missed this week'))
                + '</div><div class="row g-3"><div class="col-lg-6"><div class="itflow-dispatch-chart"><canvas aria-label="Tickets in and closed each day this week" role="img"></canvas></div></div>'
                + '<div class="col-lg-6"><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Agent</th><th class="text-end" title="Raised for them or given to them this week">Given</th><th class="text-end">Closed</th><th class="text-end">Open</th><th class="text-end">SLA missed</th></tr></thead><tbody>'
                + (json.agents || []).map(function (a) {
                    return '<tr><td>' + dot(a.color) + esc(a.name) + '</td><td class="text-end">' + a.assigned + '</td><td class="text-end">' + a.closed + '</td><td class="text-end">' + a.open + '</td><td class="text-end">'
                        + (a.sla_missed ? '<span class="text-danger fw-semibold">' + a.sla_missed + '</span>' : '0') + '</td></tr>';
                }).join('') + '</tbody></table></div></div></div>';
            statsEl.innerHTML = html;
            if (statsChart) {
                statsChart.destroy();
                statsChart = null;
            }
            if (typeof Chart === 'undefined') {
                return;
            }
            const css = getComputedStyle(document.documentElement);
            const v = function (name, fallback) {
                return (css.getPropertyValue(name) || '').trim() || fallback;
            };
            const base = typeof itflowChartDefaults === 'function' ? itflowChartDefaults() : {grid: 'rgba(0,0,0,.1)'};
            statsChart = new Chart(statsEl.querySelector('canvas'), {
                type: 'bar',
                data: {
                    labels: json.days.map(function (d) { return d.label; }),
                    datasets: [
                        {label: 'In', data: json.days.map(function (d) { return d.future ? null : d.inbound; }), backgroundColor: v('--itflow-accent', '#007bff'), borderRadius: 4, maxBarThickness: 18},
                        {label: 'Closed', data: json.days.map(function (d) { return d.future ? null : d.closed; }), backgroundColor: v('--bs-success', '#198754'), borderRadius: 4, maxBarThickness: 18}
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {mode: 'index', intersect: false},
                    plugins: {legend: {position: 'bottom', labels: {boxWidth: 12}}},
                    scales: {
                        x: {grid: {display: false}},
                        y: {beginAtZero: true, ticks: {precision: 0}, grid: {color: base.grid}}
                    }
                }
            });
        }).catch(function (error) {
            statsEl.innerHTML = '<div class="text-danger small">' + esc(error.message) + '</div>';
        });
    }

    // ---------- the toolbar ----------

    root.querySelectorAll('[data-dispatch-view]').forEach(function (button) {
        button.addEventListener('click', function () {
            state.view = button.dataset.dispatchView;
            saveState();
            render();
        });
    });
    root.querySelectorAll('[data-dispatch-nav]').forEach(function (button) {
        button.addEventListener('click', function () {
            const step = Number(button.dataset.dispatchNav);
            state.date = step === 0 ? today() : addDays(state.date, step * (state.view === 'week' ? 7 : 1));
            render();
        });
    });
    root.querySelector('[data-dispatch-date]').addEventListener('change', function (e) {
        if (e.target.value) {
            state.date = parseYmd(e.target.value);
            render();
        }
    });
    root.querySelectorAll('[data-dispatch-agent]').forEach(function (box) {
        box.addEventListener('change', function () {
            const id = Number(box.value);
            state.hidden = state.hidden.filter(function (h) { return h !== id; });
            if (!box.checked) {
                state.hidden.push(id);
            }
            saveState();
            render();
            loadStats();
        });
    });
    root.querySelector('[data-dispatch-weekends]').addEventListener('change', function (e) {
        state.weekends = e.target.checked;
        saveState();
        if (state.view === 'week') {
            render();
        }
    });
    // The ticket's quick look can resolve, reassign or book a ticket
    document.addEventListener('hidden.bs.modal', function (e) {
        if (e.target !== scheduleModalEl && e.target !== confirmEl) {
            reloadAll();
        }
    });

    loadQueue();
    render();
    loadStats();

})();
