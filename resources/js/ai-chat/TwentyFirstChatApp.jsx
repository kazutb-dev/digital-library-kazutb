import { AgentChat, createAgentChat } from '@21st-sdk/react';
import { useChat } from '@ai-sdk/react';
import { useEffect, useMemo, useState } from 'react';

const storageKeyFor = (agent) => `21st-chat:${agent}`;

function readStoredSession(agent) {
    try {
        const raw = window.localStorage.getItem(storageKeyFor(agent));
        if (!raw) {
            return null;
        }

        const parsed = JSON.parse(raw);

        if (!parsed?.sandboxId || !parsed?.threadId) {
            return null;
        }

        return parsed;
    } catch {
        return null;
    }
}

function writeStoredSession(agent, session) {
    window.localStorage.setItem(storageKeyFor(agent), JSON.stringify(session));
}

async function postJson(url, body) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(body),
        credentials: 'same-origin',
    });

    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new Error(payload.message ?? 'Request failed');
    }

    return payload;
}

function ChatRuntime({ agent, tokenUrl, session, onNewThread }) {
    const chat = useMemo(
        () => createAgentChat({
            agent,
            tokenUrl,
            sandboxId: session.sandboxId,
            threadId: session.threadId,
        }),
        [agent, tokenUrl, session.sandboxId, session.threadId],
    );

    const { messages, sendMessage, status, stop, error } = useChat({ chat });

    return (
        <div className="flex min-h-[680px] flex-col rounded-[10px] border border-[#d6d9dd] bg-white shadow-[0_12px_32px_rgba(25,28,29,0.04)]">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-[#d6d9dd] px-5 py-4">
                <div>
                    <p className="text-xs font-semibold uppercase tracking-[0.24em] text-[#43474f]">Library AI assistant</p>
                    <p className="mt-1 text-sm text-[#43474f]">Sandbox {session.sandboxId.slice(0, 12)}... · Thread {session.threadId.slice(0, 12)}...</p>
                </div>
                <button
                    type="button"
                    onClick={onNewThread}
                    className="inline-flex min-h-11 items-center justify-center rounded-[6px] border border-[#d6d9dd] px-4 text-sm font-medium text-[#191c1d] transition hover:border-[#001e40]/20 hover:bg-[#f3f4f5]"
                >
                    New thread
                </button>
            </div>
            <div className="min-h-0 flex-1">
                <AgentChat
                    messages={messages}
                    onSend={(message) =>
                        sendMessage({
                            role: 'user',
                            parts: [{ type: 'text', text: message.content }],
                        })
                    }
                    status={status}
                    onStop={stop}
                    error={error ?? undefined}
                    colorMode="light"
                    theme={{
                        light: {
                            '--an-chat-accent': '#001e40',
                            '--an-chat-accent-foreground': '#ffffff',
                            '--an-chat-background': '#ffffff',
                            '--an-chat-surface': '#f8f9fa',
                            '--an-chat-surface-2': '#f3f4f5',
                            '--an-chat-border': '#d6d9dd',
                            '--an-chat-text': '#191c1d',
                            '--an-chat-text-muted': '#43474f',
                        },
                    }}
                />
            </div>
        </div>
    );
}

export function TwentyFirstChatApp({ agent, sessionUrl, tokenUrl, threadUrl, staffName }) {
    const [session, setSession] = useState(() => readStoredSession(agent));
    const [bootstrapError, setBootstrapError] = useState('');
    const [isBootstrapping, setIsBootstrapping] = useState(() => !readStoredSession(agent));
    const [threadCounter, setThreadCounter] = useState(0);

    useEffect(() => {
        if (session) {
            return;
        }

        let isMounted = true;

        const bootstrap = async () => {
            try {
                setBootstrapError('');
                const payload = await postJson(sessionUrl, {
                    name: 'Frontend chat',
                });

                if (!isMounted) {
                    return;
                }

                const nextSession = {
                    sandboxId: payload.sandboxId,
                    threadId: payload.threadId,
                };

                writeStoredSession(agent, nextSession);
                setSession(nextSession);
            } catch (error) {
                if (!isMounted) {
                    return;
                }

                setBootstrapError(error instanceof Error ? error.message : 'Unable to initialize the AI chat.');
            } finally {
                if (isMounted) {
                    setIsBootstrapping(false);
                }
            }
        };

        bootstrap();

        return () => {
            isMounted = false;
        };
    }, [agent, session, sessionUrl]);

    const createNewThread = async () => {
        if (!session) {
            return;
        }

        try {
            setBootstrapError('');
            const payload = await postJson(threadUrl, {
                sandboxId: session.sandboxId,
                name: 'Frontend follow-up',
            });

            const nextSession = {
                sandboxId: session.sandboxId,
                threadId: payload.threadId,
            };

            writeStoredSession(agent, nextSession);
            setSession(nextSession);
            setThreadCounter((value) => value + 1);
        } catch (error) {
            setBootstrapError(error instanceof Error ? error.message : 'Unable to create a new thread.');
        }
    };

    return (
        <section className="min-h-screen bg-[#f8f9fa] px-4 py-8 text-[#191c1d] sm:px-6 lg:px-8">
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6">
                <header className="rounded-[10px] border border-[#d6d9dd] bg-white px-6 py-6 shadow-[0_12px_32px_rgba(25,28,29,0.04)]">
                    <div className="flex flex-wrap items-start justify-between gap-6">
                        <div className="max-w-3xl">
                            <p className="inline-flex rounded-full bg-[#f3f4f5] px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em] text-[#43474f]">Internal assistance</p>
                            <h1 className="mt-4 text-3xl font-semibold tracking-tight text-[#001e40] sm:text-4xl">Library AI assistance for staff operations</h1>
                            <p className="mt-3 text-base leading-7 text-[#43474f]">
                                Staff-only assistant for operational UI work, guided support, and internal tooling across the current Digital Library workspace.
                            </p>
                        </div>
                        <div className="min-w-[240px] rounded-[8px] border border-[#d6d9dd] bg-[#f8f9fa] px-4 py-4 text-sm text-[#43474f]">
                            <p className="font-semibold text-[#191c1d]">Signed in staff</p>
                            <p className="mt-1">{staffName}</p>
                            <p className="mt-3 text-xs uppercase tracking-[0.18em] text-[#43474f]">Agent</p>
                            <p className="mt-1 font-mono text-xs text-[#191c1d]">{agent}</p>
                        </div>
                    </div>
                </header>

                {bootstrapError ? (
                    <div className="rounded-[8px] border border-[#e3c98f] bg-[#fbf7ef] px-4 py-3 text-sm text-[#5d4201]">
                        {bootstrapError}
                    </div>
                ) : null}

                {isBootstrapping || !session ? (
                    <div className="rounded-[10px] border border-[#d6d9dd] bg-white px-6 py-12 text-center text-[#43474f] shadow-[0_12px_32px_rgba(25,28,29,0.04)]">
                        Preparing sandbox and chat thread...
                    </div>
                ) : (
                    <ChatRuntime
                        key={`${session.sandboxId}:${session.threadId}:${threadCounter}`}
                        agent={agent}
                        tokenUrl={tokenUrl}
                        session={session}
                        onNewThread={createNewThread}
                    />
                )}
            </div>
        </section>
    );
}