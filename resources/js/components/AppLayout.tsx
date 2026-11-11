import { Link, NavLink, Outlet, useLocation, useNavigation } from 'react-router';
import { useUser } from '../auth';
import { config } from '../config';
import type { Notices } from '../types';
import { NoticeList } from '../ui';
import UserMenu from './UserMenu';

/**
 * Header (SPECIFICATION.md §9.3) and the messages passed by the previous screen.
 */
export default function AppLayout() {
    const user = useUser();
    const location = useLocation();
    const navigation = useNavigation();

    return (
        <>
            {navigation.state === 'loading' && <div className="loading-bar" role="progressbar" aria-label="Loading" />}

            <header className="site-header">
                {user ? (
                    <Link to="/" className="brand">
                        {config.appName}
                    </Link>
                ) : (
                    <a href={config.landingUrl} className="brand">
                        {config.appName}
                    </a>
                )}

                {user && (
                    <>
                        <nav className="nav" aria-label="Main">
                            {user.role === 'admin' && (
                                <>
                                    <NavLink to="/admin/cards">Cards</NavLink>
                                    <NavLink to="/admin/languages">Languages</NavLink>
                                    <NavLink to="/admin/import">Import / export</NavLink>
                                </>
                            )}
                            <NavLink to="/learn">Learn</NavLink>
                        </nav>
                        <UserMenu user={user} />
                    </>
                )}
            </header>

            <main className="container">
                <NoticeList notices={location.state as Notices | null} />
                <Outlet />
            </main>
        </>
    );
}
