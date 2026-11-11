import { type RouteObject, redirect } from 'react-router';
import { getUser, homePath } from './auth';
import AppLayout from './components/AppLayout';
import ErrorPage, { NotFound } from './components/ErrorPage';
import Install, { loader as installLoader } from './pages/auth/Install';
import Login, { loader as loginLoader } from './pages/auth/Login';
import Register, { loader as registerLoader } from './pages/auth/Register';
import CardPage, { loader as cardPageLoader } from './pages/admin/CardPage';
import Cards, { loader as cardsLoader } from './pages/admin/Cards';
import Import, { loader as importLoader } from './pages/admin/Import';
import ImportMapping, { loader as importMappingLoader } from './pages/admin/ImportMapping';
import ImportPreview, { loader as importPreviewLoader } from './pages/admin/ImportPreview';
import LanguageEdit, { loader as languageEditLoader } from './pages/admin/LanguageEdit';
import Languages, { loader as languagesLoader } from './pages/admin/Languages';
import LearnSession, { loader as learnSessionLoader } from './pages/learn/LearnSession';
import LearnStart, { loader as learnStartLoader } from './pages/learn/LearnStart';
import Settings, { loader as settingsLoader } from './pages/Settings';

/** Screens of the app, below /app (SPECIFICATION.md §10.2). */
export const routes: RouteObject[] = [
    {
        element: <AppLayout />,
        // Shown while the first screen loads its data.
        hydrateFallbackElement: <p className="container muted">Loading…</p>,
        children: [
            {
                // Inside the layout, so the header stays when a screen fails.
                errorElement: <ErrorPage />,
                children: [
                    {
                        index: true,
                        loader: () => {
                            const user = getUser();
                            return redirect(user ? homePath(user) : '/login');
                        },
                    },
                    { path: 'login', element: <Login />, loader: loginLoader },
                    { path: 'register', element: <Register />, loader: registerLoader },
                    { path: 'install', element: <Install />, loader: installLoader },

                    { path: 'learn', element: <LearnStart />, loader: learnStartLoader },
                    { path: 'learn/sessions/:id', element: <LearnSession />, loader: learnSessionLoader },
                    { path: 'settings', element: <Settings />, loader: settingsLoader },

                    { path: 'admin', loader: () => redirect('/admin/cards') },
                    { path: 'admin/cards', element: <Cards />, loader: cardsLoader },
                    { path: 'admin/cards/new', element: <CardPage />, loader: cardPageLoader },
                    { path: 'admin/cards/:id', element: <CardPage />, loader: cardPageLoader },
                    { path: 'admin/languages', element: <Languages />, loader: languagesLoader },
                    { path: 'admin/languages/:id', element: <LanguageEdit />, loader: languageEditLoader },
                    { path: 'admin/import', element: <Import />, loader: importLoader },
                    { path: 'admin/import/mapping', element: <ImportMapping />, loader: importMappingLoader },
                    { path: 'admin/import/preview', element: <ImportPreview />, loader: importPreviewLoader },

                    { path: '*', element: <NotFound /> },
                ],
            },
        ],
    },
];
