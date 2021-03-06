<?php
if (!empty($LoggedUser['DisableTagging']) || !check_perms('site_delete_tag')) {
    error(403);
}

$tagId = (int)$_GET['tagid'];
$groupId = (int)$_GET['groupid'];

if (!$tagId || !$groupId) {
    error(404);
}

$name = $DB->scalar("
    SELECT Name FROM tags WHERE ID = ?", $tagId
);
if ($name) {
    $DB->prepared_query("
        DELETE FROM torrents_tags_votes WHERE GroupID = ? AND TagID = ?
        ", $groupId, $tagId
    );
    $DB->prepared_query("
        DELETE FROM torrents_tags WHERE GroupID = ? AND TagID = ?
        ", $groupId, $tagId
    );
    $uses = $DB->scalar("
        SELECT count(*) FROM torrents_tags WHERE TagID = ?
        ", $tagId
    ) + $DB->scalar("
        SELECT count(*)
        FROM requests_tags rt
        INNER JOIN requests r ON (r.ID = rt.RequestID)
        WHERE r.FillerID = 0 /* TODO: change to DEFAULT NULL */
            AND rt.TagID = ?
        ", $tagId
    );
    if (!$uses) {
        (new Gazelle\Log)->general("Unused tag \"$name\" removed by user {$LoggedUser['ID']} ({$LoggedUser['Username']})");
        $DB->prepared_query("
            DELETE FROM tags WHERE ID = ?
            ", $tagId
        );
    }

    Torrents::update_hash($groupId);
    (new Gazelle\Log)->group($groupId, $LoggedUser['ID'], "Tag \"$name\" removed from group $groupId");

    // Cache the deleted tag for 5 minutes
    $Cache->cache_value('deleted_tags_'.$groupId.'_'.$LoggedUser['ID'], $name, 300);
}
header("Location: " . $_SERVER['HTTP_REFERER'] ?? "torrents.php?id={$groupId}");
