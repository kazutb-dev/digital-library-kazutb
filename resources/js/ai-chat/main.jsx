import React from 'react';
import { createRoot } from 'react-dom/client';
import { TwentyFirstChatApp } from './TwentyFirstChatApp.jsx';
import '@21st-sdk/react/styles.css';

const mount = document.getElementById('twentyfirst-ai-chat-root');

if (mount) {
    const root = createRoot(mount);

    root.render(
        <React.StrictMode>
            <TwentyFirstChatApp
                agent={mount.dataset.agent ?? 'frontend-dev-agent'}
                sessionUrl={mount.dataset.sessionUrl ?? ''}
                tokenUrl={mount.dataset.tokenUrl ?? ''}
                threadUrl={mount.dataset.threadUrl ?? ''}
                staffName={mount.dataset.staffName ?? 'Library staff'}
            />
        </React.StrictMode>,
    );
}