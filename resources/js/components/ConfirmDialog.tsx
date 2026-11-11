import { type ReactNode, useCallback, useEffect, useRef, useState } from 'react';

export interface ConfirmOptions {
    message: ReactNode;
    /** Text of the confirming button. */
    confirmLabel?: string;
    /** Shows the confirming button in red, for actions that delete something. */
    danger?: boolean;
}

interface Request extends ConfirmOptions {
    resolve: (confirmed: boolean) => void;
}

/**
 * An in-page replacement for window.confirm(). Render the returned element somewhere in the
 * page, then `if (await confirm({ message })) { ... }`.
 */
export function useConfirm(): [(options: ConfirmOptions) => Promise<boolean>, ReactNode] {
    const [request, setRequest] = useState<Request | null>(null);

    const confirm = useCallback(
        (options: ConfirmOptions) => new Promise<boolean>((resolve) => setRequest({ ...options, resolve })),
        [],
    );

    const dialog = request && (
        <ConfirmDialog
            {...request}
            onClose={(confirmed) => {
                setRequest(null);
                request.resolve(confirmed);
            }}
        />
    );

    return [confirm, dialog];
}

/**
 * A modal <dialog>: the browser keeps the focus inside it and closes it on Escape.
 * Cancel has the focus at first, so pressing Enter does not delete anything by accident.
 */
function ConfirmDialog({ message, confirmLabel = 'OK', danger = false, onClose }: ConfirmOptions & { onClose: (confirmed: boolean) => void }) {
    const dialog = useRef<HTMLDialogElement>(null);
    const cancel = useRef<HTMLButtonElement>(null);

    useEffect(() => {
        if (!dialog.current?.open) {
            dialog.current?.showModal();
        }
        cancel.current?.focus();
    }, []);

    return (
        <dialog
            ref={dialog}
            className="confirm-dialog"
            // The buttons submit the method="dialog" form, which closes the dialog with the button's value.
            onClose={() => onClose(dialog.current?.returnValue === 'confirm')}
            // A click on the backdrop lands on the <dialog> itself.
            onClick={(event) => event.target === dialog.current && dialog.current.close()}
        >
            <form method="dialog">
                <p>{message}</p>
                <div className="buttons">
                    <button type="submit" value="confirm" className={danger ? 'button danger' : 'button'}>
                        {confirmLabel}
                    </button>
                    <button type="submit" value="cancel" className="button secondary" ref={cancel}>
                        Cancel
                    </button>
                </div>
            </form>
        </dialog>
    );
}
