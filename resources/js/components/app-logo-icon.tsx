import type { ImgHTMLAttributes } from 'react';

/**
 * Logo du club.
 *
 * Servi depuis `public/` plutôt qu'importé par Vite : c'est la même image que
 * les favicons, et elle n'a pas à être rejouée dans le bundle à chaque build.
 */
export default function AppLogoIcon(
    props: ImgHTMLAttributes<HTMLImageElement>,
) {
    return <img src="/logo.png" alt="" {...props} />;
}
