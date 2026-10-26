import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
            <path
                fillRule="evenodd"
                clipRule="evenodd"
                d="M15.5 4C13.0147 4 11 6.01472 11 8.5V31.5C11 33.9853 13.0147 36 15.5 36H24.5C26.9853 36 29 33.9853 29 31.5V8.5C29 6.01472 26.9853 4 24.5 4H15.5ZM15 12.8L26.5 20L15 27.2V22L20.5 20L15 18V12.8Z"
            />
        </svg>
    );
}
