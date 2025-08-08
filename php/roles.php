<?php
// Ensure session is started before including this
if (!isset($_SESSION)) session_start();

// Load roles from session
$user_roles = $_SESSION['user_roles'] ?? [];

// Define access levels for each role
$role_access = [
    'admin' => ['admin', 'reviewer', 'referee'],   // Admin has access to all
    'reviewer' => ['reviewer', 'referee'],         // Reviewer inherits referee
    'referee' => ['referee'],                      // Referee only
    // Add more roles here as needed:
    // 'moderator' => ['moderator', 'viewer'],
    // 'viewer' => ['viewer']
];

// Calculate the effective roles (including inherited)
$effective_roles = [];
foreach ($user_roles as $role) {
    if (isset($role_access[$role])) {
        $effective_roles = array_merge($effective_roles, $role_access[$role]);
    }
}
$effective_roles = array_unique($effective_roles);

// Function to check if user has a given role (directly or inherited)
function hasRole(string $role): bool {
    global $effective_roles;
    return in_array($role, $effective_roles);
}
?>
