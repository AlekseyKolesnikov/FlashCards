import { Link, isRouteErrorResponse, useRouteError } from 'react-router';
import { ApiError } from '../api';
import { useTitle } from '../ui';

/** Shown in place of a screen whose data could not be loaded. */
export default function ErrorPage() {
    const error = useRouteError();
    const status = error instanceof ApiError ? error.status : isRouteErrorResponse(error) ? error.status : 500;

    return <Problem status={status} detail={error instanceof Error ? error.message : null} />;
}

export function NotFound() {
    return <Problem status={404} detail={null} />;
}

function Problem({ status, detail }: { status: number; detail: string | null }) {
    const title = status === 404 ? 'Not found' : status === 403 ? 'No access' : 'Something went wrong';
    useTitle(title);

    return (
        <div className="card narrow">
            <h1>{title}</h1>
            <p className="muted">
                {status === 404 && 'This page does not exist, or it was removed.'}
                {status === 403 && 'Your account cannot open this page.'}
                {status !== 404 && status !== 403 && (detail || 'Please try again.')}
            </p>
            <p>
                <Link to="/">Go to the start page</Link>
            </p>
        </div>
    );
}
