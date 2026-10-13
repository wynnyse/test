document.addEventListener('DOMContentLoaded', () => {
    const msgStream = document.getElementById('chat-stream');
    const messageInput = document.getElementById('message-input');
    const attachmentInput = document.getElementById('message-attachment');
    const filePreviewBar = document.getElementById('file-preview-bar');
    const filePreviewName = document.getElementById('file-preview-name');
    const removeFileBtn = document.getElementById('remove-file-btn');
    const chatForm = document.getElementById('chat-form');

    // 1. Auto-scroll message stream to the bottom (latest message)
    function scrollToBottom() {
        if (msgStream) {
            msgStream.scrollTop = msgStream.scrollHeight;
        }
    }
    scrollToBottom();

    // 2. File Attachment Preview & Removal
    if (attachmentInput) {
        attachmentInput.addEventListener('change', () => {
            if (attachmentInput.files && attachmentInput.files.length > 0) {
                const fileName = attachmentInput.files[0].name;
                filePreviewName.textContent = `Attached: ${fileName}`;
                filePreviewBar.style.display = 'flex';
            }
        });
    }

    if (removeFileBtn) {
        removeFileBtn.addEventListener('click', () => {
            if (attachmentInput) {
                attachmentInput.value = '';
            }
            if (filePreviewBar) {
                filePreviewBar.style.display = 'none';
            }
        });
    }

    // 3. Auto-resize textarea as user types
    if (messageInput) {
        messageInput.addEventListener('input', () => {
            messageInput.style.height = 'auto';
            messageInput.style.height = `${Math.min(messageInput.scrollHeight, 120)}px`;
        });

        // Submit form on Enter key (Shift + Enter for new line)
        messageInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (messageInput.value.trim() !== '' || (attachmentInput && attachmentInput.files.length > 0)) {
                    chatForm.submit();
                }
            }
        });
    }
});