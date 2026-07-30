<?php
require_once 'includes/config.php';
require_once 'includes/timetable_data.php';

// Two data sources: the official Excel import, and whatever our scheduler produced in the DB.
$source = ($_GET['source'] ?? 'official') === 'generated' ? 'generated' : 'official';
$modes = [
    'master' => 'Master View (All Classes)',
    'year' => 'By Year',
    'class' => 'By Class',
    'faculty' => 'By Faculty',
    'all_faculty' => 'All Faculty',
    'room' => 'By Room',
];

$view_mode = $_GET['mode'] ?? 'master';
if (!isset($modes[$view_mode])) { $view_mode = 'master'; }
$selected_key = trim((string) ($_GET['key'] ?? ''));
$selected_id = intval($_GET['id'] ?? 0);

function mode_link(string $source, string $mode): string {
    return 'view.php?source=' . urlencode($source) . '&mode=' . urlencode($mode);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Timetable - Smart Timetable</title>
    <?php common_styles(); ?>
    <style>
        .timetable-container { overflow-x: auto; }
        .timetable-grid { width: 100%; border-collapse: collapse; font-size: 13px; border: 1px solid #ddd; }
        .timetable-grid th { background: #00BFA5; color: white; padding: 10px 8px; text-align: center; font-weight: 600; border: 1px solid #00a896; }
        .timetable-grid td { padding: 8px; text-align: center; border: 1px solid #e0e0e0; min-width: 110px; height: 60px; vertical-align: middle; }
        .timetable-grid .time-cell { background: #f8f9fa; font-weight: 600; color: #555; text-align: left; padding-left: 12px; min-width: 160px; }
        .timetable-grid .class-slot { background: #e8f5e9; }
        .timetable-grid .class-slot.lab { background: #e3f2fd; border: 2px solid #2196f3; }
        .timetable-grid .empty-slot { color: #ccc; }

        .break-cell { background: #fff8e1 !important; color: #856404 !important; font-style: italic; }
        .lunch-cell { background: #ffecb3 !important; color: #856404 !important; font-weight: 600; }
        .break-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; margin-top: 2px; }

        .subject-name { font-weight: 600; color: #333; font-size: 12px; }
        .faculty-name { color: #666; font-size: 11px; }
        .class-name { color: #3498db; font-size: 11px; }
        .room-name { color: #9c27b0; font-size: 10px; font-weight: 600; }
        .building-name { color: #888; font-size: 10px; }
        .lab-badge { font-size: 10px; color: #2196f3; font-weight: 600; }
        .energy-badge { font-size: 9px; color: #27ae60; background: #d4edda; padding: 1px 4px; border-radius: 2px; display: inline-block; margin-top: 2px; }

        .source-switch { display: inline-flex; gap: 4px; background: #f3e5f5; border-radius: 12px; padding: 4px; margin-bottom: 6px; }
        .source-seg { display: flex; align-items: center; gap: 10px; padding: 10px 18px; border-radius: 9px; text-decoration: none; color: #6B1B5E; font-size: 14px; font-weight: 600; transition: background .18s ease, color .18s ease, box-shadow .18s ease; }
        .source-seg:hover { background: rgba(107, 27, 94, 0.08); }
        .source-seg svg { width: 18px; height: 18px; fill: currentColor; flex-shrink: 0; }
        .source-seg .seg-text { display: flex; flex-direction: column; line-height: 1.25; text-align: left; }
        .source-seg .seg-sub { font-size: 11px; font-weight: 500; opacity: .75; }
        .source-seg.active { background: #6B1B5E; color: white; box-shadow: 0 2px 8px rgba(107, 27, 94, 0.35); }
        .source-seg.active:hover { background: #6B1B5E; }

        .view-toggle { display: flex; gap: 4px; margin: 4px 0 20px; border-bottom: 2px solid #eee; }
        .view-tab { padding: 9px 16px; text-decoration: none; color: #777; font-size: 13px; font-weight: 600; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: color .15s ease, border-color .15s ease; }
        .view-tab:hover { color: #00897B; }
        .view-tab.active { color: #00897B; border-bottom-color: #00BFA5; }

        @media (max-width: 600px) {
            .source-switch { flex-direction: column; width: 100%; }
            .source-seg { justify-content: center; }
        }

        .filter-bar { display: flex; gap: 12px; align-items: center; margin-bottom: 15px; flex-wrap: wrap; }
        .filter-bar select { padding: 6px 10px; border: 1px solid #ddd; border-radius: 3px; min-width: 220px; font-size: 13px; }

        .schedule-meta { color: #666; margin: -6px 0 18px; font-size: 13px; }
        .course-list { margin-top: 24px; max-width: 700px; }
        .course-list table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .course-list th, .course-list td { padding: 8px 10px; border: 1px solid #e0e0e0; text-align: left; }
        .course-list th { background: #f3e5f5; color: #6B1B5E; }
        .legend { display: flex; gap: 20px; margin-top: 15px; font-size: 12px; flex-wrap: wrap; }
        .legend-item { display: flex; align-items: center; gap: 6px; }
        .legend-box { width: 16px; height: 16px; border: 1px solid #ddd; }
        .no-data { text-align: center; padding: 50px 20px; color: #777; }
        .print-header { display: none; }

        .master-grid { width: 100%; border-collapse: collapse; font-size: 11px; border: 1px solid #ddd; }
        .master-grid th { background: #6B1B5E; color: white; padding: 8px 4px; text-align: center; font-weight: 600; border: 1px solid #5a1850; font-size: 11px; }
        .master-grid th.time-header { background: #00BFA5; min-width: 130px; }
        .master-grid th.class-header { background: #7B2D6E; font-size: 10px; }
        .master-grid td { padding: 4px; text-align: center; border: 1px solid #e0e0e0; min-width: 90px; height: 50px; vertical-align: middle; }
        .master-grid .time-cell { background: #f8f9fa; font-weight: 600; color: #555; text-align: left; padding-left: 10px; min-width: 130px; font-size: 11px; }
        .master-grid .class-slot { background: #e8f5e9; }
        .master-grid .class-slot.lab { background: #e3f2fd; border: 2px solid #2196f3; }
        .master-grid .empty-slot { color: #ccc; font-size: 11px; }
        .master-grid .subject-name { font-weight: 600; color: #333; font-size: 10px; }
        .master-grid .faculty-name { color: #666; font-size: 9px; }
        .master-grid .class-name { color: #3498db; font-size: 9px; }
        .master-grid .room-name { color: #9c27b0; font-size: 8px; font-weight: 600; }
        .master-grid .lab-badge { font-size: 8px; color: #2196f3; font-weight: 600; }
        .master-grid .energy-badge { font-size: 8px; color: #27ae60; background: #d4edda; padding: 1px 3px; border-radius: 2px; display: inline-block; margin-top: 1px; }

        .master-day-section { margin-bottom: 25px; }
        .master-day-title { font-size: 16px; font-weight: 700; color: #6B1B5E; margin-bottom: 8px; padding: 8px 12px; background: linear-gradient(135deg, #f3e5f5, #e8d5f0); border-radius: 4px; border-left: 4px solid #6B1B5E; }
        .master-empty { text-align: center; padding: 60px 20px; color: #888; }
        .master-empty h3 { color: #6B1B5E; margin-bottom: 10px; }

        .year-title { font-size: 20px; font-weight: 700; color: #6B1B5E; margin: 28px 0 12px; padding-bottom: 6px; border-bottom: 2px solid #e8d5f0; }
        .year-title:first-child { margin-top: 0; }
        .year-title .year-sub { font-size: 12px; font-weight: 500; color: #888; margin-left: 8px; }

        /* Also in print.css — inline so a cached stylesheet can't leave the saved
           PDF sideways. Bare `landscape` makes Chrome rotate the content instead. */
        @page { size: A4 landscape; margin: 10mm; }

        @media print {
            /* The source switch is chrome, not data — never print it. Declared here
               (last in the cascade) so it wins regardless of print.css load order. */
            .source-switch, .source-seg { display: none !important; }

            /* One day per page. fitPrintTables() scales each day down to the page
               width, so a day now fits a single page and the break is clean rather
               than letting rows spill onto the next one. */
            .master-day-section {
                page-break-after: always;
                break-after: page;
                page-break-inside: avoid;
                break-inside: avoid;
                margin-bottom: 0;
            }
            /* Without this the trailing break emits a blank final page. */
            .master-day-section:last-of-type {
                page-break-after: auto;
                break-after: auto;
            }
            .master-day-title { page-break-after: avoid; background: #f3e5f5 !important; -webkit-print-color-adjust: exact; }
            /* The preceding day section already forced a break, so a year heading
               starts its own page — it only needs to stay glued to its first day. */
            .year-title { page-break-after: avoid; break-after: avoid; margin-top: 0; }
            /* The dropdown that picked the year/faculty is chrome, not data. */
            .filter-bar { display: none !important; }
            .master-grid { font-size: 9px; }
            .master-grid td { height: auto; padding: 3px; }

            /* Also in print.css — repeated inline so a cached stylesheet can't
               reintroduce the split Course & Faculty table. */
            .course-list {
                page-break-before: always !important;
                break-before: page !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                margin-top: 0 !important;
            }
            .course-list table, .course-list tbody {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
        }
    </style>
</head>
<body>
    <?php svg_defs(); ?>
    <?php sidebar('view'); ?>
    <?php top_header(); ?>

    <div class="content-wrapper">
        <?php breadcrumb(['Manage Time Table', 'Student Time Table']); ?>

        <div class="content-box">
            <div class="content-box-header">
                <span>View Timetable</span>
                <button class="btn btn-print" onclick="printPage()"><svg><use href="#icon-print"/></svg> Print</button>
            </div>
            <div class="content-box-body">
                <div class="print-header">
                    <h2>Ajeenkya DY Patil University</h2>
                    <p>Academic Timetable | <?php echo date('F Y'); ?></p>
                </div>

                <div class="source-switch">
                    <a href="<?php echo mode_link('official', 'master'); ?>" class="source-seg <?php echo $source === 'official' ? 'active' : ''; ?>">
                        <svg><use href="#icon-book"/></svg>
                        <span class="seg-text">Official Timetable<span class="seg-sub">Confirmed class schedule</span></span>
                    </a>
                    <a href="<?php echo mode_link('generated', 'master'); ?>" class="source-seg <?php echo $source === 'generated' ? 'active' : ''; ?>">
                        <svg><use href="#icon-ai"/></svg>
                        <span class="seg-text">Generated Timetable<span class="seg-sub">Our scheduler's output</span></span>
                    </a>
                </div>

                <div class="view-toggle">
                    <?php foreach ($modes as $mode => $label): ?>
                        <a href="<?php echo mode_link($source, $mode); ?>" class="view-tab <?php echo $view_mode === $mode ? 'active' : ''; ?>"><?php echo $label; ?></a>
                    <?php endforeach; ?>
                </div>

                <?php require $source === 'official' ? 'includes/view_official.php' : 'includes/view_generated.php'; ?>

                <div class="legend">
                    <div class="legend-item"><div class="legend-box" style="background:#e8f5e9;"></div> Lecture</div>
                    <div class="legend-item"><div class="legend-box" style="background:#e3f2fd;border:2px solid #2196f3;"></div> Lab</div>
                    <div class="legend-item"><div class="legend-box" style="background:#fff8e1;"></div> Short Break</div>
                    <div class="legend-item"><div class="legend-box" style="background:#ffecb3;"></div> Lunch Break</div>
                </div>
            </div>
        </div>
    </div>
    <script>
        // The grid is wider than the page, so the print output clips whatever the user
        // would have to scroll to see. Shrink each table with a transform (which keeps
        // column proportions, unlike table-layout:fixed) so the full width fits.
        // A4 landscape minus 10mm margins is ~277mm of printable width.
        const PRINT_WIDTH_PX = 277 / 25.4 * 96;
        // A4 landscape (210mm) less the 10mm @page margins — the real page box.
        const PAGE_HEIGHT_PX = 190 / 25.4 * 96;
        // Ceiling on growth, so a 5-column grid doesn't become billboard text.
        const MAX_SCALE = 2.2;
        // Space the print-only page header occupies — see printHeightBudget().
        const PRINT_HEADER_PX = 26;
        // Most a row may be padded when a width-bound grid has vertical slack.
        const EXTRA_ROW_PAD_PX = 30;

        // Chrome seeds the "Save as PDF" filename from document.title, snapshotted
        // when print() is called — so printPage() sets it before printing rather
        // than in the beforeprint handler, which runs too late to be picked up.
        const PRINT_TITLE = <?php echo json_encode(preg_replace('/[\\\\\/:*?"<>|]+/', '-', ($print_doc_title ?? 'Timetable') . ' - ' . date('M Y'))); ?>;
        let savedTitle = '';

        function printPage() {
            savedTitle = document.title;
            document.title = PRINT_TITLE;
            window.print();
            document.title = savedTitle;
        }

        /**
         * Height the grid may occupy on its page. Everything sharing the page is
         * measured rather than assumed: a flat budget both wasted space (the day
         * title is far shorter than the 25mm once reserved) and overflowed page 1,
         * where the print header also has to fit — and an overflowing day section
         * is moved whole by break-inside:avoid, which is what left the first page
         * empty but for the title.
         */
        function printHeightBudget(el, single, first, last) {
            let used = 12; // slack for rounding and cell borders
            const add = node => { if (node) used += node.offsetHeight || 0; };

            // The print header only exists on the first printed page. It can't be
            // measured: beforeprint runs while screen styles still apply, where it is
            // display:none and reports 0 — so reserve the height its print CSS gives
            // it (one 12pt line plus 4px margin, rounded up).
            if (first) used += PRINT_HEADER_PX;

            // Day/faculty sections carry their own title above the grid; in By Year
            // the year heading sits above that section's first day only.
            const section = el.closest('.master-day-section');
            if (section) {
                add(section.querySelector('.master-day-title'));
                const prev = section.previousElementSibling;
                if (prev && prev.classList.contains('year-title')) add(prev);
            }

            // The legend prints after the last grid, so only that page pays for it.
            if (last) add(document.querySelector('.legend'));

            if (single) {
                document.querySelectorAll('.schedule-meta, .content-box-body > h2').forEach(add);
                add(el.parentElement && el.parentElement.querySelector('h2'));
            }
            return Math.max(200, PAGE_HEIGHT_PX - used);
        }

        function fitPrintTables() {
            const containers = document.querySelectorAll('.timetable-container');
            const single = containers.length === 1;
            containers.forEach((el, i) => {
                const table = el.querySelector('table');
                if (!table) return;
                // The table is width:100% of the container, so its own natural width
                // is only measurable with the stretch removed. Needed both to scale a
                // narrow grid UP into the empty page space and to centre it.
                table.style.width = 'auto';
                const width = table.scrollWidth;
                const height = table.scrollHeight;
                if (!width || !height) { table.style.width = ''; return; }
                // A small grid (e.g. one class, 5 days) grows until it hits the page
                // width or height, whichever binds first; MAX_SCALE just stops a tiny
                // grid from being blown up into cartoon-sized text.
                const budget = printHeightBudget(el, single, i === 0, i === containers.length - 1);
                let scale = Math.min(PRINT_WIDTH_PX / width, budget / height, MAX_SCALE);
                let boxHeight = height;

                // A wide grid (the master view has one column per class) is limited by
                // the page width, so scaling alone leaves half the page blank below it.
                // Spend that slack on taller rows instead of leaving it empty.
                const rows = table.rows.length;
                const slack = budget / scale - height;
                if (rows && slack > rows) {
                    const pad = Math.min(EXTRA_ROW_PAD_PX, slack / rows / 2);
                    table.querySelectorAll('td, th').forEach(cell => {
                        cell.style.paddingTop = cell.style.paddingBottom = pad + 'px';
                    });
                    boxHeight = table.scrollHeight;
                    scale = Math.min(PRINT_WIDTH_PX / width, budget / boxHeight, MAX_SCALE);
                }
                // Shrink the box to the table and scale from the top-left, so the grid
                // stays flush with the left margin like the rest of the page content.
                el.style.width = width + 'px';
                el.style.transform = 'scale(' + scale + ')';
                el.style.transformOrigin = 'top left';
                // A transform doesn't change the space the element reserves, so the
                // page would keep a gap under it — clamp the height to match.
                el.style.height = (boxHeight * scale) + 'px';
            });
        }

        function resetPrintTables() {
            document.querySelectorAll('.timetable-container').forEach(el => {
                const table = el.querySelector('table');
                if (table) {
                    table.style.width = '';
                    table.querySelectorAll('td, th').forEach(cell => {
                        cell.style.paddingTop = cell.style.paddingBottom = '';
                    });
                }
                el.style.transform = '';
                el.style.transformOrigin = '';
                el.style.height = '';
                el.style.width = '';
                el.style.margin = '';
            });
        }

        window.addEventListener('beforeprint', fitPrintTables);
        window.addEventListener('afterprint', resetPrintTables);

        // Headless PDF export (chrome --print-to-pdf) never dispatches beforeprint, so
        // the scaling above would be skipped and the exported file would not match what
        // the Print button produces. ?printfit=1 applies it on load for that path.
        if (new URLSearchParams(location.search).get('printfit') === '1') {
            window.addEventListener('load', fitPrintTables);
        }
    </script>
</body>
</html>
