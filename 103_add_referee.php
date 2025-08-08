<?php
// 103_add_referee.php – Add referees to a match with crew size and search

require_once 'php/db.php';
$pdo = getDatabaseConnection();

$game_id = $_GET['game_id'] ?? 0;
if (!$game_id) {
    die("Missing game ID.");
}

// Fetch all users
$users = $pdo->query("SELECT id, name FROM review_user ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$user_map = [];
foreach ($users as $u) {
    $user_map[$u['id']] = $u['name'];
}

// Crew presets
$crew_presets = [
    'football' => [
        '5'   => ['R', 'U', 'DJ', 'LJ', 'BJ'],
        '6C'  => ['R', 'C', 'U', 'DJ', 'LJ', 'BJ'],
        '6D'  => ['R', 'U', 'DJ', 'LJ', 'SJ', 'FJ'],
        '7'   => ['R', 'U', 'DJ', 'LJ', 'SJ', 'FJ', 'BJ'],
        '8'   => ['R', 'C', 'U', 'DJ', 'LJ', 'SJ', 'FJ', 'BJ'],
    ],
    'flag' => [
        '3_IFAF' => ['R', 'DJ', 'FJ'],
        '3_USA'  => ['R', 'SJ', 'FJ'],
        '4'      => ['R', 'DJ', 'SJ', 'FJ'],
        '5L'     => ['R', 'DJ', 'LJ', 'SJ', 'FJ'],
        '5D'     => ['R', 'DJ', 'SJ', 'FJ', 'BJ'],
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Referees</title>
    <!-- Filename: 103_add_referee.php -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <style>
        .autocomplete-results {
            position: absolute;
            background: white;
            border: 1px solid #ccc;
            width: 100%;
            max-height: 150px;
            overflow-y: auto;
            z-index: 1000;
        }
        .autocomplete-results div {
            padding: 5px;
            cursor: pointer;
        }
        .autocomplete-results div:hover {
            background-color: #f0f0f0;
        }
    </style>
</head>
<body class="p-4">
<div class="container">
    <form method="post" action="save_referee_crew.php">
        <input type="hidden" name="game_id" value="<?= (int)$game_id ?>">

        <!-- Step 1: Sport Type -->
        <div class="form-group">
            <label><strong>Select Sport:</strong></label><br>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="sport" id="sport_football" value="football">
                <label class="form-check-label" for="sport_football">American Football</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="sport" id="sport_flag" value="flag">
                <label class="form-check-label" for="sport_flag">Flag Football</label>
            </div>
        </div>

        <!-- Step 2: Crew Size -->
        <div class="form-group" id="crewsize_container" style="display:none;">
            <label><strong>Select Crew Size:</strong></label>
            <select name="crew_size" id="crew_size" class="form-control" required></select>
        </div>

        <!-- Step 3: Position fields -->
        <div class="mt-4" id="crew_fields_container"></div>

        <button type="submit" class="btn btn-success mt-3">Save Crew</button>
    </form>
</div>

<script>
    const allUsers = <?= json_encode($users) ?>;
    const crewPresets = <?= json_encode($crew_presets) ?>;

    const sportRadios = document.querySelectorAll('input[name="sport"]');
    const crewsizeContainer = document.getElementById('crewsize_container');
    const crewsizeSelect = document.getElementById('crew_size');
    const crewFieldsContainer = document.getElementById('crew_fields_container');

    let currentCrew = [];

    // Handle sport selection
    sportRadios.forEach(radio => {
        radio.addEventListener('change', () => {
            const sport = radio.value;
            const sizes = Object.keys(crewPresets[sport]);

            crewsizeSelect.innerHTML = '';
            sizes.forEach(size => {
                const option = document.createElement('option');
                option.value = size;
                option.textContent = size;
                crewsizeSelect.appendChild(option);
            });

            crewsizeContainer.style.display = 'block';
            crewFieldsContainer.innerHTML = '';
        });
    });

    // Handle crew size selection
    crewsizeSelect.addEventListener('change', () => {
        const sport = document.querySelector('input[name="sport"]:checked').value;
        const size = crewsizeSelect.value;
        currentCrew = crewPresets[sport][size];

        crewFieldsContainer.innerHTML = '';
        currentCrew.forEach(pos => {
            const group = document.createElement('div');
            group.className = 'form-group position-relative';
            group.innerHTML = `
                <label>${pos}</label>
                <input type="text" name="search_${pos}" class="form-control crew-search" data-pos="${pos}" autocomplete="off">
                <input type="hidden" name="${pos}_id">
                <div class="autocomplete-results" id="auto_${pos}" style="display:none;"></div>
            `;
            crewFieldsContainer.appendChild(group);
        });

        enableAutocomplete();
    });

    function enableAutocomplete() {
        document.querySelectorAll('.crew-search').forEach(input => {
            const pos = input.dataset.pos;
            const resultBox = document.getElementById('auto_' + pos);
            const hidden = document.querySelector(`input[name="${pos}_id"]`);

            input.addEventListener('input', () => {
                const term = input.value.toLowerCase();
                if (term.length < 2) {
                    resultBox.style.display = 'none';
                    return;
                }

                resultBox.innerHTML = '';
                const matches = allUsers.filter(u => u.name.toLowerCase().includes(term));
                matches.forEach(u => {
                    const div = document.createElement('div');
                    div.textContent = u.name;
                    div.onclick = () => {
                        input.value = u.name;
                        hidden.value = u.id;
                        resultBox.style.display = 'none';
                    };
                    resultBox.appendChild(div);
                });

                resultBox.style.display = 'block';
            });

            document.addEventListener('click', (e) => {
                if (!resultBox.contains(e.target) && e.target !== input) {
                    resultBox.style.display = 'none';
                }
            });
        });
    }
</script>
</body>
</html>
