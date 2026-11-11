import { FieldError } from '../ui';

/** Username, email and password fields of registration and the install page (SPECIFICATION.md §9.1). */
export default function AccountFields({ errors }: { errors: Record<string, string> }) {
    return (
        <>
            <label htmlFor="username">Username</label>
            <input id="username" name="username" required autoFocus autoComplete="username" minLength={3} maxLength={32} />
            <p className="hint">3–32 characters: letters, digits, dashes and underscores.</p>
            <FieldError error={errors.username} />

            <label htmlFor="email">Email</label>
            <input id="email" name="email" type="email" required autoComplete="email" maxLength={255} />
            <FieldError error={errors.email} />

            <label htmlFor="password">Password</label>
            <input id="password" name="password" type="password" required autoComplete="new-password" minLength={8} />
            <p className="hint">At least 8 characters.</p>
            <FieldError error={errors.password} />

            <label htmlFor="password_confirmation">Repeat password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autoComplete="new-password" />
        </>
    );
}
