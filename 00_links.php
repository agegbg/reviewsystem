<!DOCTYPE html>
<html>
<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/css/bootstrap.min.css"
        integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">

    <title>Filförteckning</title>
    <style>
        .file-link {
            font-size: 1.5em;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Filförteckning</h1>
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>HTML-filer</th>
                    <th>PHP-filer</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $files = scandir(__DIR__);
                $html_files = [];
                $php_files = [];

                foreach ($files as $file) {
                    if (is_file($file)) {
                        $extension = pathinfo($file, PATHINFO_EXTENSION);
                        if ($extension === 'html') {
                            $html_files[] = $file;
                        } elseif ($extension === 'php') {
                            $php_files[] = $file;
                        }
                    }
                }

                sort($html_files);
                sort($php_files);

                $num_files = max(count($html_files), count($php_files));

                for ($i = 0; $i < $num_files; $i++) {
                    $html_file = isset($html_files[$i]) ? $html_files[$i] : '';
                    $php_file = isset($php_files[$i]) ? $php_files[$i] : '';
                    echo '<tr>';
                    echo '<td>';
                    if ($html_file) {
                        echo '<a href="' . $html_file . '" class="file-link" target="_blank">' . $html_file . '</a>';
                    }
                    echo '</td>';
                    echo '<td>';
                    if ($php_file) {
                        echo '<a href="' . $php_file . '" class="file-link" target="_blank">' . $php_file . '</a>';
                    }
                    echo '</td>';
                    echo '</tr>';
                }
                ?>
            </tbody>
        </table>
    </div>

    <!-- Optional JavaScript -->
    <!-- jQuery first, then Popper.js, then Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"
        integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo"
        crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.14.7/dist/umd/popper.min.js"
        integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQ
