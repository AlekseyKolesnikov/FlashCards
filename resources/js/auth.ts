// The logged-in user. Loaded once when the app starts, then kept up to date by login and logout.

import { useSyncExternalStore } from 'react';
import { redirect } from 'react-router';
import { config } from './config';
import type { User } from './types';

let currentUser: User | null = null;
const listeners = new Set<() => void>();

export function getUser(): User | null {
    return currentUser;
}

export function setUser(user: User | null): void {
    currentUser = user;
    listeners.forEach((listener) => listener());
}

export function useUser(): User | null {
    return useSyncExternalStore(
        (listener) => {
            listeners.add(listener);
            return () => listeners.delete(listener);
        },
        getUser,
    );
}

/** The start page of each role. */
export function homePath(user: User): string {
    return user.role === 'admin' ? '/admin/cards' : '/learn';
}

/**
 * For loaders of screens that need a logged-in user, and optionally the admin role.
 * Guests go to the login page, which returns them here afterwards.
 */
export function requireUser(request: Request, role?: 'admin'): User {
    const user = getUser();

    if (!user) {
        const url = new URL(request.url);
        const back = url.pathname.slice(config.appPath.length) + url.search;
        throw redirect(`/login?next=${encodeURIComponent(back)}`);
    }

    if (role && user.role !== role) {
        throw new Response('Forbidden', { status: 403 });
    }

    return user;
}
