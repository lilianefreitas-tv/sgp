const board = document.querySelector('[data-kanban-board]');

if (board && board.dataset.canMove === '1') {
    let draggedCard = null;

    const clearDropState = () => {
        board.querySelectorAll('[data-kanban-dropzone]').forEach((dropzone) => {
            dropzone.classList.remove('is-drag-over');
        });
    };

    board.querySelectorAll('[data-kanban-card]').forEach((card) => {
        card.addEventListener('dragstart', (event) => {
            draggedCard = card;
            card.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', card.dataset.taskId);
        });

        card.addEventListener('dragend', () => {
            card.classList.remove('is-dragging');
            clearDropState();
            draggedCard = null;
        });
    });

    board.querySelectorAll('[data-kanban-dropzone]').forEach((dropzone) => {
        dropzone.addEventListener('dragover', (event) => {
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            clearDropState();
            dropzone.classList.add('is-drag-over');
        });

        dropzone.addEventListener('dragleave', (event) => {
            if (!dropzone.contains(event.relatedTarget)) {
                dropzone.classList.remove('is-drag-over');
            }
        });

        dropzone.addEventListener('drop', async (event) => {
            event.preventDefault();
            clearDropState();

            if (!draggedCard) {
                return;
            }

            const currentColumn = draggedCard.closest('[data-kanban-column]');
            const targetStatus = dropzone.dataset.kanbanDropzone;

            if (currentColumn?.dataset.kanbanColumn === targetStatus) {
                return;
            }

            draggedCard.classList.add('is-saving');

            try {
                const response = await fetch(draggedCard.dataset.moveUrl, {
                    method: 'PATCH',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ status: targetStatus }),
                });

                if (!response.ok) {
                    const payload = await response.json().catch(() => ({}));
                    throw new Error(payload.message || 'Não foi possível movimentar a tarefa.');
                }

                window.location.reload();
            } catch (error) {
                draggedCard.classList.remove('is-saving');
                window.alert(error.message);
            }
        });
    });
}
