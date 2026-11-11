import '../css/app.css';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { createBrowserRouter } from 'react-router';
import { RouterProvider } from 'react-router/dom';
import { api } from './api';
import { setUser } from './auth';
import { config } from './config';
import { routes } from './routes';
import type { User } from './types';

const root = createRoot(document.getElementById('root')!);

// The logged-in user is known before the first screen loads, so screens can check it at once.
api.get<{ user: User | null }>('/user').then(
    ({ user }) => {
        setUser(user);
        // The basename includes the folder the app is served from (SPECIFICATION.md §10.2).
        const router = createBrowserRouter(routes, { basename: config.appPath });

        root.render(
            <StrictMode>
                <RouterProvider router={router} />
            </StrictMode>,
        );
    },
    () => {
        root.render(
            <main className="container">
                <p className="notice danger">The app could not start. Please reload the page.</p>
            </main>,
        );
    },
);
