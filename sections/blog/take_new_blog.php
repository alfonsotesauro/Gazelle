<?php

use Gazelle\Manager\Notification;

authorize();

if (!check_perms('admin_manage_blog')) {
    error(403);
}

if (empty($_POST['title']) || empty($_POST['body'])) {
    error('You must have a title and body for the blog post.');
}

$ThreadID = !isset($_POST['thread']) || $_POST['thread'] === '' ? '' : max(0, intval($_POST['thread']));

if ($ThreadID > 0) {
    if (!$DB->scalar("SELECT ForumID FROM forums_topics WHERE ID = ?", $ThreadID)) {
        error('No such thread exists!');
    }
}
elseif ($ThreadID === '') {
    $forum = new \Gazelle\Forum(ANNOUNCEMENT_FORUM_ID);
    $ThreadID = $forum->addThread($LoggedUser['ID'], $_POST['title'], $_POST['body']);
    if ($ThreadID < 1) {
        error(0);
    }
}
else {
    $ThreadID = null;
}

$blogMan = new Gazelle\Manager\Blog;
$blog = $blogMan->create([
    'title'     => trim($_POST['title']),
    'body'      => trim($_POST['body']),
    'important' => isset($_POST['important']) ? 1 : 0,
    'threadId'  => $ThreadID,
    'userId'    => $LoggedUser['ID'],
]);

if (isset($_POST['subscribe']) && $ThreadID !== null && $ThreadID > 0) {
    $subMan = new Gazelle\Manager\Subscription($LoggedUser['ID']);
    $subMan->subscribe($ThreadID);
}
$notification = new Notification($LoggedUser['ID']);
$notification->push($notification->pushableUsers(), $blog->title(), $blog->body(), SITE_URL . '/index.php', Notification::BLOG);

send_irc("PRIVMSG " . MOD_CHAN . " :!New blog article: " . $blog->title());

header('Location: blog.php');
