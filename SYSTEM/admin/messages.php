<?php
session_start();

require_once "../connection.php";
require_once "../functions.php";

check_login($con);

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Admin') {
    header("Location: ../login.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function escape($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$admin_id = $_SESSION['user_id'] ?? 1;
$active_client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;

// Fetch Recent Conversations
$conversations = [];
$conv_query = "
    SELECT 
        u.id AS client_id,
        u.user_name AS client_name,
        u.email AS client_email,
        m.message AS last_message,
        m.sent_at AS last_message_time,
        m.sender_id AS last_sender_id,
        (
            SELECT COUNT(*) 
            FROM messages 
            WHERE sender_id = u.id AND receiver_id = $admin_id AND is_read = 0
        ) AS unread_count
    FROM users u
    INNER JOIN messages m ON (m.sender_id = u.id OR m.receiver_id = u.id)
    WHERE u.user_type = 'Client'
    AND m.id = (
        SELECT MAX(id) 
        FROM messages 
        WHERE (sender_id = u.id AND receiver_id = $admin_id)
           OR (sender_id = $admin_id AND receiver_id = u.id)
    )
    ORDER BY m.sent_at DESC";

$conv_result = mysqli_query($con, $conv_query);
if ($conv_result) {
    while ($row = mysqli_fetch_assoc($conv_result)) {
        $conversations[] = $row;
    }
}

if ($active_client_id === 0 && !empty($conversations)) {
    $active_client_id = (int)$conversations[0]['client_id'];
}

// Fetch Active Client Profile
$active_client = null;
if ($active_client_id > 0) {
    $client_stmt = mysqli_prepare($con, "SELECT id, user_name, email FROM users WHERE id = ?");
    mysqli_stmt_bind_param($client_stmt, "i", $active_client_id);
    mysqli_stmt_execute($client_stmt);
    $active_client = mysqli_fetch_assoc(mysqli_stmt_get_result($client_stmt));
    mysqli_stmt_close($client_stmt);
}

// Fetch Messages Thread
$chat_messages = [];
if ($active_client_id > 0) {
    $read_stmt = mysqli_prepare($con, "UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
    mysqli_stmt_bind_param($read_stmt, "ii", $active_client_id, $admin_id);
    mysqli_stmt_execute($read_stmt);
    mysqli_stmt_close($read_stmt);

    $msg_query = "
        SELECT 
            m.*,
            ma.id AS attachment_id,
            ma.file_path,
            ma.file_name,
            ma.file_type
        FROM messages m
        LEFT JOIN message_attachments ma ON m.id = ma.message_id
        WHERE (m.sender_id = $admin_id AND m.receiver_id = $active_client_id)
           OR (m.sender_id = $active_client_id AND m.receiver_id = $admin_id)
        ORDER BY m.sent_at ASC";

    $msg_result = mysqli_query($con, $msg_query);
    if ($msg_result) {
        while ($row = mysqli_fetch_assoc($msg_result)) {
            $msg_id = $row['id'];
            if (!isset($chat_messages[$msg_id])) {
                $chat_messages[$msg_id] = [
                    'id' => $row['id'],
                    'sender_id' => $row['sender_id'],
                    'receiver_id' => $row['receiver_id'],
                    'message' => $row['message'],
                    'is_read' => $row['is_read'],
                    'sent_at' => $row['sent_at'],
                    'attachments' => []
                ];
            }
            if (!empty($row['attachment_id'])) {
                $chat_messages[$msg_id]['attachments'][] = [
                    'id' => $row['attachment_id'],
                    'file_path' => $row['file_path'],
                    'file_name' => $row['file_name'],
                    'file_type' => $row['file_type']
                ];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Messages | Kvffie Arts</title>
    <link rel="icon" type="image/x-icon" href="../imgs/mascot.png">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@1,900&family=Montserrat:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/messages.css">
    <script src="../script/messages.js" defer></script>
</head>

<body class="messages-page">

<div class="admin-layout">

    <?php include __DIR__ . '/sidebar.php'; ?>

    <main class="msg-layout">

        <!-- LEFT PANEL: Conversations Thread List -->
        <aside class="msg-panel">
            <header class="msg-panel__header">
                <h2>Messages</h2>
            </header>

            <div class="msg-threads-list" id="conversations-list">
                <?php if (!empty($conversations)): ?>
                    <?php foreach ($conversations as $conv): ?>
                        <?php $is_active = ((int)$conv['client_id'] === $active_client_id); ?>
                        <a href="messages.php?client_id=<?= (int)$conv['client_id'] ?>" 
                           class="msg-thread-card <?= $is_active ? 'is-active' : '' ?>"
                           data-name="<?= escape($conv['client_name']) ?>">
                            
                            <div class="msg-thread-card__avatar">
                                <?= strtoupper(substr($conv['client_name'], 0, 1)) ?>
                            </div>

                            <div class="msg-thread-card__content">
                                <div class="msg-thread-card__top">
                                    <strong><?= escape($conv['client_name']) ?></strong>
                                    <span class="time"><?= date('M d', strtotime($conv['last_message_time'])) ?></span>
                                </div>
                                <div class="msg-thread-card__bottom">
                                    <p><?= ($conv['last_sender_id'] == $admin_id ? 'You: ' : '') . escape($conv['last_message']) ?></p>
                                    <?php if ((int)$conv['unread_count'] > 0): ?>
                                        <span class="msg-unread-badge"><?= (int)$conv['unread_count'] ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="msg-threads-empty">
                        <p>No messages yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </aside>

        <!-- RIGHT PANEL: Chat Workspace -->
        <section class="msg-workspace">
            <?php if ($active_client): ?>
                
                <!-- Chat Header Bar -->
                <header class="msg-header">
                    <div class="msg-header__client">
                        <div class="avatar">
                            <?= strtoupper(substr($active_client['user_name'], 0, 1)) ?>
                        </div>
                        <div>
                            <h3><?= escape($active_client['user_name']) ?></h3>
                            <p><?= escape($active_client['email']) ?></p>
                        </div>
                    </div>
                </header>

                <!-- Message Stream -->
                <div class="msg-stream" id="chat-stream">
                    <?php if (!empty($chat_messages)): ?>
                        <?php foreach ($chat_messages as $msg): ?>
                            <?php $is_outgoing = ((int)$msg['sender_id'] === (int)$admin_id); ?>
                            <div class="msg-row <?= $is_outgoing ? 'is-outgoing' : 'is-incoming' ?>">
                                <div class="msg-bubble">
                                    <p><?= nl2br(escape($msg['message'])) ?></p>

                                    <?php if (!empty($msg['attachments'])): ?>
                                        <div class="msg-attachments">
                                            <?php foreach ($msg['attachments'] as $att): ?>
                                                <?php 
                                                    $ext = strtolower(pathinfo($att['file_name'], PATHINFO_EXTENSION));
                                                    $is_img = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']);
                                                ?>
                                                <?php if ($is_img): ?>
                                                    <a href="../<?= escape($att['file_path']) ?>" target="_blank" class="att-img">
                                                        <img src="../<?= escape($att['file_path']) ?>" alt="<?= escape($att['file_name']) ?>" loading="lazy">
                                                    </a>
                                                <?php else: ?>
                                                    <a href="../<?= escape($att['file_path']) ?>" download class="att-file">
                                                        &#128206; <?= escape($att['file_name']) ?>
                                                    </a>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="msg-meta">
                                        <span><?= date('g:i A', strtotime($msg['sent_at'])) ?></span>
                                        <?php if ($is_outgoing): ?>
                                            <span>&bull; <?= $msg['is_read'] ? 'Read' : 'Sent' ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="msg-empty-thread">
                            <p>Send a message to start chatting with <strong><?= escape($active_client['user_name']) ?></strong>.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Footer Composer Bar -->
                <footer class="msg-footer">
                    <form id="chat-form" action="send_message.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="receiver_id" value="<?= (int)$active_client['id'] ?>">

                        <div class="file-preview-bar" id="file-preview-bar" style="display: none;">
                            <span id="file-preview-name"></span>
                            <button type="button" id="remove-file-btn">&times;</button>
                        </div>

                        <div class="msg-composer">
                            <label for="message-attachment" class="msg-composer__attach" title="Attach file">
                                &#128206;
                                <input type="file" id="message-attachment" name="attachment" accept="image/jpeg,image/png,image/webp,image/gif,application/pdf">
                            </label>

                            <textarea name="message" id="message-input" placeholder="Type a message..." rows="1" required></textarea>

                            <button type="submit" class="button button--primary send-btn">Send</button>
                        </div>
                    </form>
                </footer>

            <?php else: ?>
                <div class="msg-empty-selection">
                    <h3>Messages</h3>
                    <p>Select a conversation from the list to start messaging.</p>
                </div>
            <?php endif; ?>
        </section>

    </main>
</div>

</body>
</html>