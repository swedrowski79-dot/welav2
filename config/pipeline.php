<?php

return [
    'pipeline' => [
        'jobs' => [
            'import_all' => [
                'script' => 'run_import_all.php',
                'label' => 'Import starten',
                'run_type_label' => 'Import',
                'button_class' => 'btn-primary',
                'help' => 'Laedt alle aktuellen Importquellen in die RAW-Tabellen.',
                'contains' => ['Produkt-Import', 'Kategorie-Import'],
            ],
            'import_products' => [
                'script' => 'run_import_products.php',
                'label' => 'Produkt-Import',
                'run_type_label' => 'Produkt-Import',
                'button_class' => 'btn-outline-primary',
                'help' => 'Importiert nur Produktdaten und Artikel-Uebersetzungen.',
            ],
            'import_categories' => [
                'script' => 'run_import_categories.php',
                'label' => 'Kategorie-Import',
                'run_type_label' => 'Kategorie-Import',
                'button_class' => 'btn-outline-primary',
                'help' => 'Importiert nur Kategorien und Kategorie-Uebersetzungen.',
            ],
            'merge' => [
                'script' => 'run_merge.php',
                'label' => 'Merge starten',
                'run_type_label' => 'Merge',
                'button_class' => 'btn-outline-primary',
                'help' => 'Fuehrt RAW-Quellen zu den Stage-Grunddaten zusammen.',
            ],
            'expand' => [
                'script' => 'run_expand.php',
                'label' => 'Expand starten',
                'run_type_label' => 'Expand + Delta',
                'button_class' => 'btn-outline-secondary',
                'help' => 'Erzeugt expandierte Stage-Daten und startet im selben Lauf die Delta-Berechnung.',
                'contains' => ['Stage-Expand', 'Delta-Berechnung und Queue-Befuellung'],
            ],
            'xt_mirror' => [
                'script' => 'run_xt_mirror.php',
                'label' => 'XT Mirror Refresh',
                'run_type_label' => 'XT Mirror Refresh',
                'button_class' => 'btn-outline-info',
                'help' => 'Liest Produkte, Kategorien, Medien und Dokumente aus XT ueber die API und aktualisiert die lokalen Mirror-Tabellen.',
            ],
            'delta' => [
                'script' => 'run_delta.php',
                'label' => 'Delta starten',
                'run_type_label' => 'Delta',
                'button_class' => 'btn-outline-dark',
                'help' => 'Optionaler manueller Delta-Neulauf fuer erneute Queue-Befuellung ohne neuen Expand-Lauf.',
            ],
            'export_queue_worker' => [
                'script' => 'run_export_queue.php',
                'label' => 'Export Worker',
                'run_type_label' => 'Export Worker',
                'button_class' => 'btn-outline-dark',
                'help' => 'Verarbeitet Queue-Eintraege, schreibt in XT und bestaetigt danach den Export-Status.',
            ],
             'full_pipeline' => [
                 'script' => 'run_full_pipeline.php',
                 'label' => 'Full Pipeline',
                 'run_type_label' => 'Full Pipeline inkl. Export',
                 'button_class' => 'btn-dark',
                 'help' => 'Startet Import, Merge, XT Mirror, Expand inklusive Delta und anschliessend den Export Worker.',
                 'sequence' => [
                     'import_all',
                     'merge',
                     'xt_mirror',
                     'expand',
                     'export_queue_worker',
                 ],
                 'contains' => [
                     'Import aller Produkte und Kategorien',
                     'Merge der RAW-Daten in die Stage',
                     'XT Mirror Refresh',
                     'Expand inklusive Delta und Queue-Befuellung',
                     'Export Worker',
                 ],
             ],
        ],
        'sections' => [
            [
                'title' => 'Full Pipeline (empfohlen)',
                'description' => 'Sammelaktion: startet die komplette Standardreihenfolge von Import bis Export in einem Hintergrundlauf.',
                'jobs' => [
                    'full_pipeline',
                ],
            ],
            [
                'title' => '1. Import (AFS -> RAW)',
                'description' => 'Importiert Produkt- und Kategoriequellen in die RAW-Tabellen.',
                'jobs' => [
                    'import_all',
                    'import_products',
                    'import_categories',
                ],
            ],
            [
                'title' => '2. Merge (RAW -> Stage)',
                'description' => 'Fuehrt die importierten RAW-Quellen zu den Stage-Grunddaten zusammen.',
                'jobs' => [
                    'merge',
                ],
            ],
            [
                'title' => '3. XT Mirror (XT -> lokaler Abgleich)',
                'description' => 'Liest den aktuellen XT-Zustand in die lokalen Mirror-Tabellen fuer Abgleich und Analyse.',
                'jobs' => [
                    'xt_mirror',
                ],
            ],
            [
                'title' => '4. Expand & Delta',
                'description' => 'Der Expand-Lauf besteht aus zwei Teilschritten: Stage-Daten erweitern und danach die Export Queue per Delta berechnen.',
                'jobs' => [
                    'expand',
                    'delta',
                ],
            ],
            [
                'title' => '5. Export',
                'description' => 'Der Worker verarbeitet die zuvor durch Delta erzeugten Queue-Eintraege und schreibt sie nach XT.',
                'jobs' => [
                    'export_queue_worker',
                ],
            ],
        ],
        'run_type_labels' => [
            'delta_products' => 'Delta',
            'xt_snapshot' => 'XT Mirror Refresh',
            'image_scan' => 'Bild-Scan',
            'image_upload' => 'Bild-Upload',
            'document_scan' => 'Dokument-Scan',
            'document_upload' => 'Dokument-Upload',
        ],
    ],
];
