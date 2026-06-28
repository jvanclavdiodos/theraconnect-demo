{{-- Clinician availability calendar (dashboard, bottom). Alpine-driven; talks to
     the availability.* JSON endpoints. --}}
<div class="card mt-4" x-data="availabilityCalendar()">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-calendar3 me-2"></i> My Schedule &amp; Availability</span>
        <small class="text-muted">Select a day to see appointments and block times</small>
    </div>
    <div class="card-body">
        <div class="row g-4">
            {{-- Calendar --}}
            <div class="col-lg-7">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="prevMonth()"><i class="bi bi-chevron-left"></i></button>
                    <strong x-text="monthLabel"></strong>
                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="nextMonth()"><i class="bi bi-chevron-right"></i></button>
                </div>
                <table class="table table-bordered text-center mb-0 tc-cal">
                    <thead>
                        <tr>
                            <template x-for="d in ['Mon','Tue','Wed','Thu','Fri','Sat','Sun']" :key="d">
                                <th class="small text-muted py-1" x-text="d"></th>
                            </template>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(week, wi) in weeks" :key="wi">
                            <tr>
                                <template x-for="(cell, ci) in week" :key="ci">
                                    <td class="p-0" :class="{ 'tc-cal-empty': !cell.date }">
                                        <button type="button" x-show="cell.date"
                                            class="tc-cal-day w-100 border-0"
                                            :class="{
                                                'tc-cal-blocked': cell.blocked,
                                                'tc-cal-today': cell.isToday,
                                                'tc-cal-selected': cell.date === selectedDate,
                                                'text-muted': cell.isPast,
                                            }"
                                            @click="selectDay(cell.date, cell.isPast)">
                                            <span x-text="cell.day"></span>
                                            <span class="tc-cal-dot" x-show="cell.count > 0" :title="cell.count + ' appointment(s)'" x-text="cell.count"></span>
                                            <i class="bi bi-slash-circle tc-cal-block-ic" x-show="cell.blocked"></i>
                                        </button>
                                    </td>
                                </template>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <div class="d-flex gap-3 mt-2 small text-muted flex-wrap">
                    <span><span class="tc-legend tc-legend-booked"></span> Booked</span>
                    <span><span class="tc-legend tc-legend-available"></span> Available</span>
                    <span><span class="tc-legend tc-legend-blocked"></span> Blocked</span>
                </div>
            </div>

            {{-- Day detail --}}
            <div class="col-lg-5">
                <template x-if="!selectedDate">
                    <div class="text-muted text-center py-5">Pick a day on the calendar.</div>
                </template>

                <template x-if="selectedDate && loadError">
                    <div class="alert alert-danger py-2 small mb-2" role="alert">
                        Couldn't load day details. <button type="button" class="btn btn-link btn-sm p-0 align-baseline" @click="selectDay(selectedDate, selectedIsPast)">Retry</button>
                    </div>
                </template>

                <template x-if="selectedDate && dayData">
                    <div>
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <strong x-text="prettyDate(selectedDate)"></strong>
                            <button type="button" class="btn btn-sm"
                                x-show="!selectedIsPast"
                                :class="dayData.day_blocked ? 'btn-success' : 'btn-outline-danger'"
                                @click="toggleDay()"
                                x-text="dayData.day_blocked ? 'Unblock day' : 'Block entire day'"></button>
                        </div>

                        {{-- Appointments --}}
                        <div class="mb-3">
                            <div class="small text-muted mb-1">Appointments</div>
                            <template x-if="dayData.appointments.length === 0">
                                <div class="text-muted small">None scheduled.</div>
                            </template>
                            <template x-for="appt in dayData.appointments" :key="appt.time + appt.patient">
                                <div class="d-flex align-items-center justify-content-between border rounded px-2 py-1 mb-1">
                                    <span><strong x-text="appt.time"></strong> · <span x-text="appt.patient"></span></span>
                                    <span class="badge bg-secondary text-capitalize" x-text="appt.status"></span>
                                </div>
                            </template>
                        </div>

                        {{-- Hour grid --}}
                        <div>
                            <div class="small text-muted mb-1">Hours <span x-show="!selectedIsPast">(tap available/blocked to toggle)</span></div>
                            <template x-if="dayData.hours.length === 0">
                                <div class="text-muted small">Not a working day.</div>
                            </template>
                            <div class="d-flex flex-wrap gap-2">
                                <template x-for="h in dayData.hours" :key="h.time">
                                    <button type="button"
                                        class="btn btn-sm tc-hour"
                                        :class="{
                                            'btn-info': h.status === 'booked',
                                            'btn-outline-success': h.status === 'available',
                                            'btn-danger': h.status === 'blocked',
                                        }"
                                        :disabled="h.status === 'booked' || selectedIsPast || dayData.day_blocked"
                                        :title="h.status === 'booked' ? h.patient : ''"
                                        @click="toggleHour(h.time)">
                                        <span x-text="h.time"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    .tc-cal td { vertical-align: middle; height: 44px; }
    .tc-cal-day { background: transparent; padding: 6px 2px; position: relative; cursor: pointer; }
    .tc-cal-day:hover { background: var(--bs-light); }
    .tc-cal-today { font-weight: 700; box-shadow: inset 0 0 0 2px var(--bs-primary); }
    .tc-cal-selected { background: var(--bs-primary); color: #fff; }
    .tc-cal-blocked { background: var(--bs-danger-bg-subtle, #f8d7da); }
    .tc-cal-empty { background: var(--bs-light); }
    .tc-cal-dot { position: absolute; top: 2px; right: 4px; font-size: .65rem; background: var(--bs-info); color:#fff; border-radius: 999px; padding: 0 5px; }
    .tc-cal-block-ic { position: absolute; bottom: 2px; right: 4px; font-size: .7rem; color: var(--bs-danger); }
    .tc-legend { display:inline-block; width:12px; height:12px; border-radius:3px; vertical-align:middle; margin-right:4px; }
    .tc-legend-booked { background: var(--bs-info); }
    .tc-legend-available { background: var(--bs-success); }
    .tc-legend-blocked { background: var(--bs-danger); }
    .tc-hour { min-width: 64px; }
</style>
@endpush

@push('scripts')
<script>
function availabilityCalendar() {
    return {
        year: 0, month: 0, monthLabel: '',
        weeks: [],
        selectedDate: null, selectedIsPast: false, dayData: null, loadError: false,
        urls: {
            month: "{{ route('availability.month') }}",
            day: "{{ route('availability.day') }}",
            toggleDay: "{{ route('availability.toggleDay') }}",
            toggleHour: "{{ route('availability.toggleHour') }}",
        },
        csrf: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        init() {
            const now = new Date();
            this.year = now.getFullYear();
            this.month = now.getMonth() + 1;
            this.loadMonth();
        },
        pad(n) { return String(n).padStart(2, '0'); },
        ym() { return `${this.year}-${this.pad(this.month)}`; },
        todayStr() {
            const t = new Date();
            return `${t.getFullYear()}-${this.pad(t.getMonth() + 1)}-${this.pad(t.getDate())}`;
        },
        prettyDate(ds) {
            const [y, m, d] = ds.split('-').map(Number);
            return new Date(y, m - 1, d).toLocaleDateString(undefined,
                { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
        },
        async loadMonth() {
            try {
                const res = await fetch(`${this.urls.month}?month=${this.ym()}`, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const data = await res.json();
                this.buildGrid(data.days || {});
            } catch (e) {
                // Network failure or 5xx: leave the previous month's grid in
                // place but clear the count dots so the clinician sees the
                // calendar is stale rather than a frozen/hung UI. Toggling
                // months will retry.
                this.weeks = this.weeks.map(week => week.map(cell => ({ ...cell, count: 0, blocked: false })));
            }
        },
        buildGrid(daysMap) {
            const first = new Date(this.year, this.month - 1, 1);
            const startWeekday = (first.getDay() + 6) % 7; // Monday = 0
            const daysInMonth = new Date(this.year, this.month, 0).getDate();
            const today = this.todayStr();
            const cells = [];
            for (let i = 0; i < startWeekday; i++) cells.push({ date: null });
            for (let d = 1; d <= daysInMonth; d++) {
                const ds = `${this.year}-${this.pad(this.month)}-${this.pad(d)}`;
                const info = daysMap[ds] || {};
                cells.push({
                    date: ds, day: d,
                    blocked: !!info.blocked, count: info.count || 0,
                    isToday: ds === today, isPast: ds < today,
                });
            }
            while (cells.length % 7 !== 0) cells.push({ date: null });
            const weeks = [];
            for (let i = 0; i < cells.length; i += 7) weeks.push(cells.slice(i, i + 7));
            this.weeks = weeks;
            this.monthLabel = first.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
        },
        prevMonth() { if (--this.month < 1) { this.month = 12; this.year--; } this.reset(); this.loadMonth(); },
        nextMonth() { if (++this.month > 12) { this.month = 1; this.year++; } this.reset(); this.loadMonth(); },
        reset() { this.selectedDate = null; this.dayData = null; },
        async selectDay(date, isPast) {
            if (!date) return;
            this.selectedDate = date;
            this.selectedIsPast = isPast;
            this.dayData = null;
            this.loadError = false;
            try {
                const res = await fetch(`${this.urls.day}?date=${date}`, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                this.dayData = await res.json();
            } catch (e) {
                // Show the retry banner above the day panel rather than
                // leaving the clinician staring at a perpetual spinner / empty
                // state with no indication that the fetch failed.
                this.loadError = true;
            }
        },
        async post(url, body) {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf },
                body: JSON.stringify(body),
            });
            if (!res.ok) return null;
            return res.json();
        },
        async toggleDay() {
            const data = await this.post(this.urls.toggleDay, { date: this.selectedDate });
            if (data) { this.dayData = data; this.loadMonth(); }
        },
        async toggleHour(hour) {
            const data = await this.post(this.urls.toggleHour, { date: this.selectedDate, hour });
            if (data) { this.dayData = data; this.loadMonth(); }
        },
    };
}
</script>
@endpush
