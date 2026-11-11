import { useEffect, useRef, useState } from 'react';
import { Link, useNavigate } from 'react-router';
import { api } from '../api';
import { setUser } from '../auth';
import type { User } from '../types';

/**
 * The username in the header. It opens a menu with Settings and Log out, which closes on a
 * click outside it, on Escape, and after an item is chosen (SPECIFICATION.md §9.3).
 */
export default function UserMenu({ user }: { user: User }) {
    const [open, setOpen] = useState(false);
    const menu = useRef<HTMLDivElement>(null);
    const button = useRef<HTMLButtonElement>(null);
    const navigate = useNavigate();

    useEffect(() => {
        if (!open) {
            return;
        }

        const onPointerDown = (event: PointerEvent) => {
            if (!menu.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        };
        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setOpen(false);
                button.current?.focus();
            }
        };
        // Moving the keyboard focus out of the menu closes it too.
        const onFocusIn = (event: FocusEvent) => {
            if (!menu.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        };

        document.addEventListener('pointerdown', onPointerDown);
        document.addEventListener('keydown', onKeyDown);
        document.addEventListener('focusin', onFocusIn);

        return () => {
            document.removeEventListener('pointerdown', onPointerDown);
            document.removeEventListener('keydown', onKeyDown);
            document.removeEventListener('focusin', onFocusIn);
        };
    }, [open]);

    async function logOut() {
        setOpen(false);
        await api.post('/logout');
        setUser(null);
        navigate('/login', { state: { notice: 'You are logged out.' } });
    }

    return (
        <div className="user-menu header-end" ref={menu}>
            <button
                ref={button}
                type="button"
                className="user-menu-button"
                aria-expanded={open}
                aria-controls="user-menu"
                onClick={() => setOpen(!open)}
            >
                {user.username} <span aria-hidden="true">▾</span>
            </button>

            {open && (
                <ul id="user-menu" className="user-menu-list">
                    <li>
                        <Link to="/settings" onClick={() => setOpen(false)}>
                            Settings
                        </Link>
                    </li>
                    <li>
                        <button type="button" onClick={logOut}>
                            Log out
                        </button>
                    </li>
                </ul>
            )}
        </div>
    );
}
