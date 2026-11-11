import { type LoaderFunctionArgs, useLocation, useNavigate, useParams, useSearchParams } from 'react-router';
import { requireUser } from '../../auth';
import { useGo, useTitle } from '../../ui';
import CardForm from './CardForm';

export function loader({ request }: LoaderFunctionArgs) {
    requireUser(request, 'admin');

    return null;
}

/**
 * Add or edit a card on its own screen. Saving and cancelling return to the card list
 * with its filters (the list passes its URL as "back").
 */
export default function CardPage() {
    const { id } = useParams();
    const [params] = useSearchParams();
    const location = useLocation();
    const navigate = useNavigate();
    const go = useGo();
    const back = (location.state as { back?: string } | null)?.back ?? '/admin/cards';
    useTitle(id ? 'Edit card' : 'Add card');

    // "Save and add another" keeps both languages and the complexity (§5.2).
    const number = (name: string) => Number(params.get(name)) || undefined;
    const defaults = { lang1_id: number('lang1_id'), lang2_id: number('lang2_id'), complexity: number('complexity') };

    return (
        <CardForm
            // A new form after "Save and add another".
            key={location.key}
            cardId={id ? Number(id) : undefined}
            defaults={defaults}
            onSaved={({ card, message, warning }, another) => {
                if (another) {
                    go(`/admin/cards/new?lang1_id=${card.lang1_id}&lang2_id=${card.lang2_id}&complexity=${card.complexity}`, {
                        notice: message,
                        warning,
                        back,
                    });
                } else {
                    go(back, { notice: message, warning });
                }
            }}
            onCancel={() => navigate(back)}
        />
    );
}
