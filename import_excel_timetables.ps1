param(
    [string]$WorkbookPath = (Join-Path $PSScriptRoot '2-3-4 years_SOE_Odd term_AY_26-27_CSE_AIDS AND CS (1).xls'),
    [string]$OutputPath = (Join-Path $PSScriptRoot 'data\imported_timetables.json'),
    [string]$FacultyCourseOutputPath = (Join-Path $PSScriptRoot 'FACULTY_COURSE_COMBINATIONS.md')
)

$ErrorActionPreference = 'Stop'

function Get-CellText($sheet, $row, $column) {
    return ([string]$sheet.Cells.Item($row, $column).Text).Trim()
}

function Get-SafeFileName($value) {
    return ($value -replace '[^A-Za-z0-9]+', '-').Trim('-').ToLowerInvariant()
}

function Format-MarkdownCell($value) {
    return (([string]$value).Trim() -replace '[\r\n]+', '<br>' -replace '\|', '\\|')
}

if (-not (Test-Path -LiteralPath $WorkbookPath)) {
    throw "Workbook not found: $WorkbookPath"
}

$excel = $null
$workbook = $null
try {
    $excel = New-Object -ComObject Excel.Application
    $excel.Visible = $false
    $excel.DisplayAlerts = $false
    $workbook = $excel.Workbooks.Open((Resolve-Path -LiteralPath $WorkbookPath))
    $schedules = @()

    foreach ($sheet in @($workbook.Worksheets)) {
        $headerRow = $null
        for ($candidateRow = 1; $candidateRow -le 12; $candidateRow++) {
            if ((Get-CellText $sheet $candidateRow 1) -match '(?i)time\s*/\s*day') {
                $headerRow = $candidateRow
                break
            }
        }
        if (-not $headerRow) { continue }

        $headers = @{}
        for ($column = 2; $column -le 6; $column++) {
            $day = Get-CellText $sheet $headerRow $column
            if ($day) { $headers[$column] = $day }
        }

        if ($headers.Count -eq 0) { continue }

        $sessions = @()
        $timeSlots = @()
        for ($row = $headerRow + 1; $row -le $headerRow + 17; $row += 2) {
            $time = Get-CellText $sheet $row 1
            if (-not $time) { continue }
            $timeSlots += $time

            foreach ($column in $headers.Keys) {
                $cell = $sheet.Cells.Item($row, $column)
                $entry = ([string]$cell.Text).Trim()
                if (-not $entry) { continue }

                $isBreak = $entry -match '(?i)break|lunch'
                # A timetable hour occupies a time row plus its following "Room no." row.
                # Excel merges four rows for a two-hour activity and two rows for a one-hour activity.
                $durationSlots = [Math]::Max(1, [int]($cell.MergeArea.Rows.Count / 2))
                $sessions += [PSCustomObject]@{
                    day = $headers[$column]
                    time = $time
                    entry = $entry
                    is_break = $isBreak
                    duration_slots = $durationSlots
                }
            }
        }

        $courseMap = @()
        for ($row = $headerRow + 20; $row -le $headerRow + 50; $row++) {
            $course = Get-CellText $sheet $row 2
            $faculty = Get-CellText $sheet $row 4
            if ($course -or $faculty) {
                $courseMap += [PSCustomObject]@{ course = $course; faculty = $faculty }
            }
        }

        $className = (Get-CellText $sheet 7 4) -replace '(?i)^class\s*:\s*', ''
        if (-not $className) { $className = $sheet.Name }
        $schedules += [PSCustomObject]@{
            id = Get-SafeFileName $className
            class_name = $className
            program = (Get-CellText $sheet 6 3) -replace '(?i)^program name\s*:\s*', ''
            semester = (Get-CellText $sheet 7 3) -replace '(?i)^semester\s*:\s*', ''
            effective_from = Get-CellText $sheet 6 5
            time_slots = $timeSlots
            sessions = $sessions
            course_faculty = $courseMap
        }
    }

    $outputDirectory = Split-Path -Parent $OutputPath
    if (-not (Test-Path -LiteralPath $outputDirectory)) {
        New-Item -ItemType Directory -Path $outputDirectory | Out-Null
    }

    $json = [PSCustomObject]@{
        source_file = Split-Path -Leaf $WorkbookPath
        imported_at = (Get-Date).ToString('yyyy-MM-dd HH:mm:ss')
        schedules = $schedules
    } | ConvertTo-Json -Depth 6
    [System.IO.File]::WriteAllText($OutputPath, $json, (New-Object System.Text.UTF8Encoding($false)))

    $report = @(
        '# Faculty and Course Combinations'
        ''
        "Source workbook: ``$(Split-Path -Leaf $WorkbookPath)``"
        ''
        "Generated: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
        ''
        'Each table reproduces the course-to-faculty mapping stated in its corresponding Excel worksheet.'
        ''
    )
    foreach ($schedule in $schedules) {
        $report += "## $($schedule.class_name)"
        $report += ''
        $report += '| Course | Faculty |'
        $report += '| --- | --- |'
        foreach ($combination in $schedule.course_faculty) {
            $report += "| $(Format-MarkdownCell $combination.course) | $(Format-MarkdownCell $combination.faculty) |"
        }
        $report += ''
    }
    [System.IO.File]::WriteAllText($FacultyCourseOutputPath, ($report -join [Environment]::NewLine), (New-Object System.Text.UTF8Encoding($false)))

    Write-Output "Imported $($schedules.Count) timetables to $OutputPath"
    Write-Output "Created faculty/course list at $FacultyCourseOutputPath"
}
finally {
    if ($workbook) { $workbook.Close($false); [void][Runtime.InteropServices.Marshal]::ReleaseComObject($workbook) }
    if ($excel) { $excel.Quit(); [void][Runtime.InteropServices.Marshal]::ReleaseComObject($excel) }
}
