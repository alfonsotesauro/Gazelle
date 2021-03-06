<?php
/***************************************************************
* Temp handler for changing the category for a single torrent.
****************************************************************/

authorize();
if (!check_perms('users_mod')) {
    error(403);
}

$OldGroupID = (int)$_POST['oldgroupid'];
$TorrentID = (int)$_POST['torrentid'];
$Title = trim($_POST['title']);
$OldCategoryID = (int)$_POST['oldcategoryid'];
$NewCategoryID = (int)$_POST['newcategoryid'];
if (!$OldGroupID || !$NewCategoryID || !$TorrentID || empty($Title)) {
    error(0);
}

switch ($Categories[$NewCategoryID-1]) {
    case 'Music':
        $ArtistName = db_string(trim($_POST['artist']));
        $Year = trim($_POST['year']);
        $ReleaseType = trim($_POST['releasetype']);
        if (empty($Year) || empty($ArtistName) || !is_number($Year) || empty($ReleaseType) || !is_number($ReleaseType)) {
            error(0);
        }
        list($ArtistID, $AliasID, $Redirect, $ArtistName) = $DB->row('
            SELECT ArtistID, AliasID, Redirect, Name
            FROM artists_alias
            WHERE Name LIKE ?
            ', $ArtistName
        );
        if (!$DB->has_results()) {
            $Redirect = 0;
            $ArtistManager = new \Gazelle\Manager\Artist;
            list($ArtistID, $AliasID) = $ArtistManager->createArtist($AliasName);
        } else {
            list($ArtistID, $AliasID, $Redirect, $ArtistName) = $DB->next_record();
            if ($Redirect) {
                $AliasID = $ArtistID;
            }
        }

        $DB->prepared_query("
            INSERT INTO torrents_group
                   (Name, Year, ReleaseType, CategoryID, WikiBody, WikiImage)
            VALUES (?,    ?,    ?,           1,         '',       '')
            ", $Title, $Year, $ReleaseType
        );
        $GroupID = $DB->inserted_id();

        $DB->prepared_query("
            INSERT INTO torrents_artists
                   (GroupID, ArtistID, AliasID, UserID, Importance)
            VALUES (?,       ?,        ?,       ?,      1)
            ", $GroupID, $ArtistID, $AliasID, $LoggedUser['ID']
        );
        break;
    case 'Audiobooks':
    case 'Comedy':
        $Year = trim($_POST['year']);
        if (empty($Year) || !is_number($Year)) {
            error(0);
        }
        $DB->prepared_query("
            INSERT INTO torrents_group
                   (Name, Year, CategoryID, WikiBody, WikiImage)
            VALUES (?,    ?,    ?,          '',       ''))
            ", $Title, $Year, $NewCategoryID
        );
        $GroupID = $DB->inserted_id();
        break;
    case 'Applications':
    case 'Comics':
    case 'E-Books':
    case 'E-Learning Videos':
        $DB->prepared_query("
            INSERT INTO torrents_group
                   (Name, CategoryID, WikiBody, WikiImage)
            VALUES (?,    ?,          '',       '')
            ", $Title, $NewCategoryID
        );
        $GroupID = $DB->inserted_id();
        break;
}

$DB->prepared_query('
    UPDATE torrents SET
        GroupID = ?
    WHERE ID = ?
    ', $GroupID, $TorrentID
);

// Delete old group if needed
if ($DB->scalar('SELECT ID FROM torrents WHERE GroupID = ?', $OldGroupID)) {
    Torrents::update_hash($OldGroupID);
} else {
    // TODO: votes etc.
    $DB->prepared_query("
        UPDATE comments SET
            PageID = ?
        WHERE Page = 'torrents' AND PageID = ?
        ", $GroupID, $OldGroupID
    );
    Torrents::delete_group($OldGroupID);
    $Cache->delete_value("torrent_comments_{$GroupID}_catalogue_0");
}

$DB->prepared_query('
    UPDATE group_log SET
        GroupID = ?
    WHERE GroupID = ?
    ', $GroupID, $OldGroupID
);

Torrents::update_hash($GroupID);

$Cache->delete_value("torrent_download_$TorrentID");

(new Gazelle\Log)->group($GroupID, $LoggedUser['ID'], "category changed from $OldCategoryID to $NewCategoryID, merged from group $OldGroupID")
    ->general("Torrent $TorrentID was changed to category $NewCategoryID by " . $LoggedUser['Username']);

header("Location: torrents.php?id=$GroupID");
