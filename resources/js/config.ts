// Settings the page resources/views/app.blade.php passes to the app.

export interface Range {
    min: number;
    max: number;
    default: number;
}

export interface AppConfig {
    appName: string;
    landingUrl: string;
    /** Path of the app, including the folder it is served from, e.g. "/flashcards/app". */
    appPath: string;
    /** Path of the JSON API, e.g. "/flashcards/api". */
    apiPath: string;
    /** Path of the login cookies, usually the folder the app is served from, e.g. "/flashcards". */
    cookiePath: string;
    registrationOpen: boolean;
    complexity: Range;
    sessionSize: Range;
    postponeSessions: Range;
}

declare global {
    interface Window {
        flashcards: AppConfig;
    }
}

export const config: AppConfig = window.flashcards;

/** Numbers from min to max, e.g. the complexity choices. */
export function range({ min, max }: Range): number[] {
    return Array.from({ length: max - min + 1 }, (_, i) => min + i);
}
