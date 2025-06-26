<?php
// search.php: AJAX endpoint for Melody Matrix advanced search & autocomplete
require_once 'db.php';
header('Content-Type: application/json');

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$genre = isset($_GET['genre']) ? trim($_GET['genre']) : '';
$artist = isset($_GET['artist']) ? trim($_GET['artist']) : '';
$date = isset($_GET['date']) ? trim($_GET['date']) : '';
$type = isset($_GET['type']) ? trim($_GET['type']) : 'all'; // track, artist, album, genre, all

$params = [];
$where = [];

if ($q !== '') {
    $where[] = "(m.title LIKE :q OR m.artist LIKE :q OR m.album LIKE :q OR m.genre LIKE :q)";
    $params[':q'] = "%$q%";
}
if ($genre !== '') {
    $where[] = "m.genre = :genre";
    $params[':genre'] = $genre;
}
if ($artist !== '') {
    $where[] = "m.artist = :artist";
    $params[':artist'] = $artist;
}
if ($date !== '') {
    $where[] = "DATE(m.release_date) = :date";
    $params[':date'] = $date;
}

$sql = "SELECT m.id, m.title, m.artist, m.album, m.genre, m.release_date FROM music m";
if ($where) {
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " ORDER BY m.title LIMIT 10";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tracks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// For autocomplete, also return unique artists, albums, genres
$autocomplete = [
    'artists' => [],
    'albums' => [],
    'genres' => []
];
if ($q !== '') {
    $autoSql = "SELECT DISTINCT artist, album, genre FROM music WHERE (title LIKE :q OR artist LIKE :q OR album LIKE :q OR genre LIKE :q) LIMIT 10";
    $autoStmt = $pdo->prepare($autoSql);
    $autoStmt->execute([':q' => "%$q%"]);
    while ($row = $autoStmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['artist']) $autocomplete['artists'][] = $row['artist'];
        if ($row['album']) $autocomplete['albums'][] = $row['album'];
        if ($row['genre']) $autocomplete['genres'][] = $row['genre'];
    }
    $autocomplete['artists'] = array_unique($autocomplete['artists']);
    $autocomplete['albums'] = array_unique($autocomplete['albums']);
    $autocomplete['genres'] = array_unique($autocomplete['genres']);
}

// --- Personalized Recommendations API ---
if (isset($_GET['recommend'])) {
    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    $recs = [];
    if ($userId) {
        // 1. Recently played genres
        $recentSql = "SELECT DISTINCT m.genre FROM play_history h JOIN music m ON h.track_id = m.id WHERE h.user_id = :uid ORDER BY h.played_at DESC LIMIT 3";
        $recentStmt = $pdo->prepare($recentSql);
        $recentStmt->execute([':uid' => $userId]);
        $recentGenres = $recentStmt->fetchAll(PDO::FETCH_COLUMN);
        // 2. Popular in user's genres
        if ($recentGenres) {
            $in = str_repeat('?,', count($recentGenres) - 1) . '?';
            $sql = "SELECT m.*, COUNT(l.id) as like_count FROM music m LEFT JOIN likes l ON l.track_id = m.id WHERE m.genre IN ($in) GROUP BY m.id ORDER BY like_count DESC, RAND() LIMIT 6";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($recentGenres);
            $recs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        // 3. Fallback: popular overall
        if (count($recs) < 6) {
            $sql = "SELECT m.*, COUNT(l.id) as like_count FROM music m LEFT JOIN likes l ON l.track_id = m.id GROUP BY m.id ORDER BY like_count DESC, RAND() LIMIT 10";
            $stmt = $pdo->query($sql);
            $more = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($more as $row) {
                if (count($recs) >= 10) break;
                if (!in_array($row['id'], array_column($recs, 'id'))) $recs[] = $row;
            }
        }
    } else {
        // Not logged in: show popular tracks
        $sql = "SELECT m.*, COUNT(l.id) as like_count FROM music m LEFT JOIN likes l ON l.track_id = m.id GROUP BY m.id ORDER BY like_count DESC, RAND() LIMIT 10";
        $stmt = $pdo->query($sql);
        $recs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    echo json_encode(['recommendations' => $recs]);
    exit;
}

echo json_encode([
    'tracks' => $tracks,
    'autocomplete' => $autocomplete
]);
