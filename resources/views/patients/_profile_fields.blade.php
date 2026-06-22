@php $p = $patient ?? null; @endphp

<div class="col-md-4">
    <label for="gender" class="form-label">Gender</label>
    <select id="gender" name="gender" class="form-select">
        <option value="">—</option>
        @foreach (\App\Models\Patient::GENDERS as $g)
            <option value="{{ $g }}" @selected(old('gender', $p?->gender) === $g)>{{ $g }}</option>
        @endforeach
    </select>
</div>
<div class="col-md-4">
    <label for="educational_attainment" class="form-label">Educational Attainment</label>
    <select id="educational_attainment" name="educational_attainment" class="form-select">
        <option value="">—</option>
        @foreach (\App\Models\Patient::EDUCATION_LEVELS as $level)
            <option value="{{ $level }}" @selected(old('educational_attainment', $p?->educational_attainment) === $level)>{{ $level }}</option>
        @endforeach
    </select>
</div>
<div class="col-md-4">
    <label for="employment_status" class="form-label">Employment Status</label>
    <select id="employment_status" name="employment_status" class="form-select">
        <option value="">—</option>
        @foreach (\App\Models\Patient::EMPLOYMENT_STATUSES as $status)
            <option value="{{ $status }}" @selected(old('employment_status', $p?->employment_status) === $status)>{{ $status }}</option>
        @endforeach
    </select>
</div>
<div class="col-12">
    <label for="personal_issues" class="form-label">Personal Issues</label>
    <textarea id="personal_issues" name="personal_issues" class="form-control" rows="3"
        placeholder="Concerns the patient wants to share…">{{ old('personal_issues', $p?->personal_issues) }}</textarea>
</div>
