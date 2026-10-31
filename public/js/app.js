// Confirmation dialogs: <form data-confirm="Question?"> asks before submitting.
// <form id="x" data-confirm-selected> asks about the checkboxes that belong to it (form="x").
document.addEventListener('submit', (event) => {
    const form = event.target;
    let message = form.dataset.confirm;

    if (form.dataset.confirmSelected !== undefined) {
        const count = document.querySelectorAll(`input[form="${form.id}"]:checked`).length;

        if (count === 0) {
            event.preventDefault();
            alert('Select at least one card first.');
            return;
        }

        message = `Delete ${count} selected card(s)? This cannot be undone.`;
    }

    if (message && !confirm(message)) {
        event.preventDefault();
    }
});

// <input type="checkbox" data-select-all="x"> ticks every checkbox that belongs to form "x".
document.addEventListener('change', (event) => {
    const formId = event.target.dataset?.selectAll;

    if (formId) {
        document.querySelectorAll(`input[form="${formId}"]`).forEach((box) => {
            box.checked = event.target.checked;
        });
    }
});
