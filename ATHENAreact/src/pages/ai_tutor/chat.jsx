import "./chat.css";
import { useState, useRef, useEffect, useCallback } from "react";
import Zorrito from "../../assets/images/Zorrito6.png";
import Zorrito2 from "../../assets/images/Zorrito_3.png";
import {
    sendMessage,
    getConversations,
    getConversation,
} from "../../api/ai";
import ChatMessage from "../../components/chat/ChatMessage";

const createWelcomeMessage = () => ({
    id: "welcome-msg",
    sender: "bot",
    text: "¡Hola! Soy ATHENA, tu tutora académica con Inteligencia Artificial. Puedo ayudarte a resolver dudas, analizar tus resultados y explicarte preguntas oficiales tipo ICFES paso a paso. ¿Qué tema te gustaría repasar hoy?",
    time: new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }),
});

const Chat = () => {
    const [messages, setMessages] = useState([createWelcomeMessage()]);
    const [inputText, setInputText] = useState("");
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState("");

    // Estados del historial de conversaciones y del Off-canvas Drawer
    const [conversations, setConversations] = useState([]);
    const [activeConversationId, setActiveConversationId] = useState(null);
    const [loadingHistory, setLoadingHistory] = useState(false);
    const [loadingConversation, setLoadingConversation] = useState(false);
    const [isHistoryOpen, setIsHistoryOpen] = useState(false);

    const messagesEndRef = useRef(null);
    const inputRef = useRef(null);

    // Auto-scroll al final del chat
    const scrollToBottom = () => {
        messagesEndRef.current?.scrollIntoView({ behavior: "smooth" });
    };

    useEffect(() => {
        scrollToBottom();
    }, [messages, loading]);

    // Consultar y refrescar la lista de conversaciones desde el backend
    const fetchConversations = useCallback(async () => {
        setLoadingHistory(true);
        try {
            const response = await getConversations();
            const list = Array.isArray(response?.data)
                ? response.data
                : Array.isArray(response)
                ? response
                : [];
            setConversations(list);
        } catch (err) {
            console.error("Error al cargar el historial de conversaciones:", err);
        } finally {
            setLoadingHistory(false);
        }
    }, []);

    // Cargar historial al montar el componente
    useEffect(() => {
        fetchConversations();
    }, [fetchConversations]);

    // Cargar una conversación específica y cerrar el drawer
    const loadConversation = async (id) => {
        if (!id || loading || loadingConversation) return;

        setLoadingConversation(true);
        setError("");
        setIsHistoryOpen(false);

        try {
            const response = await getConversation(id);
            const convData = response?.data || response;
            const rawMessages = Array.isArray(convData?.messages)
                ? convData.messages
                : [];

            const formattedMessages = rawMessages.map((msg, index) => {
                const isUserRole =
                    msg.role === "user" || msg.sender === "user";
                const formattedTime = msg.created_at
                    ? new Date(msg.created_at).toLocaleTimeString([], {
                          hour: "2-digit",
                          minute: "2-digit",
                      })
                    : "";

                return {
                    id: `${id}-${index}`,
                    sender: isUserRole ? "user" : "bot",
                    text: msg.content || msg.text || "",
                    time: formattedTime,
                };
            });

            setActiveConversationId(String(convData?._id || convData?.id || id));
            setMessages(
                formattedMessages.length > 0
                    ? formattedMessages
                    : [createWelcomeMessage()]
            );
        } catch (err) {
            console.error("Error al cargar la conversación seleccionada:", err);
            setError("No se pudo cargar la conversación seleccionada.");
        } finally {
            setLoadingConversation(false);
        }
    };

    // Iniciar un Nuevo Chat limpio y cerrar el drawer
    const handleNewChat = () => {
        if (loading || loadingConversation) return;
        setActiveConversationId(null);
        setMessages([createWelcomeMessage()]);
        setInputText("");
        setError("");
        setIsHistoryOpen(false);
        if (inputRef.current) {
            inputRef.current.focus();
        }
    };

    // Procesar y enviar mensaje
    const handleSendMessage = async (textToSend = inputText) => {
        const text = (typeof textToSend === "string" ? textToSend : inputText).trim();
        if (!text || loading) return;

        const userMsg = {
            id: Date.now(),
            sender: "user",
            text: text,
            time: new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }),
        };

        setMessages((prev) => [...prev, userMsg]);
        setInputText("");
        setError("");
        setLoading(true);

        try {
            const response = await sendMessage(text, activeConversationId);

            const newConversationId =
                response?.conversation_id ||
                response?.data?.conversation_id ||
                response?.data?._id ||
                response?.data?.id;

            if (newConversationId) {
                setActiveConversationId(String(newConversationId));
            }

            const botReply =
                response?.ai_response ||
                response?.data?.ai_response ||
                response?.data?.reply ||
                response?.reply ||
                (typeof response?.data === "string" ? response?.data : null) ||
                response?.data?.text ||
                response?.text;

            if (!botReply || typeof botReply !== "string" || !botReply.trim()) {
                throw new Error(
                    response?.message && response?.success === false
                        ? response.message
                        : "No se recibió una respuesta válida por parte de ATHENA IA."
                );
            }

            const botMsg = {
                id: Date.now() + 1,
                sender: "bot",
                text: botReply.trim(),
                time: new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }),
            };

            setMessages((prev) => [...prev, botMsg]);

            await fetchConversations();
        } catch (err) {
            console.error("Error al comunicarse con ATHENA IA:", err);

            let errorMsg =
                "Lo siento, ocurrió un problema al conectar con el servidor. Por favor intenta nuevamente.";

            if (err?.response?.data?.errors?.message?.[0]) {
                errorMsg = err.response.data.errors.message[0];
            } else if (err?.response?.data?.message) {
                errorMsg = err.response.data.message;
            } else if (err?.message) {
                errorMsg = err.message;
            }

            setError(errorMsg);

            const botErrorMsg = {
                id: Date.now() + 1,
                sender: "bot",
                text: `⚠️ Disculpa, ocurrió un problema: ${errorMsg}`,
                time: new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }),
                isError: true,
            };

            setMessages((prev) => [...prev, botErrorMsg]);
        } finally {
            setLoading(false);
            if (inputRef.current) {
                inputRef.current.focus();
            }
        }
    };

    const handleKeyDown = (e) => {
        if (e.key === "Enter" && !e.shiftKey) {
            e.preventDefault();
            handleSendMessage();
        }
    };

    const handleQuickAction = (promptText) => {
        if (loading) return;
        handleSendMessage(promptText);
    };

    const formatHistoryDate = (isoDate) => {
        if (!isoDate) return "";
        const date = new Date(isoDate);
        if (Number.isNaN(date.getTime())) return "";
        return date.toLocaleDateString("es-CO", {
            day: "2-digit",
            month: "short",
        });
    };

    return (
        <div className="main__content">
            {/* HEADER CON BOTÓN DE VER HISTORIAL A LA DERECHA */}
            <section className="tutor-header">
                <div className="tutor-header__left">
                    <div className="tutor-avatar">
                        <img src={Zorrito} alt="Tutor ATHENA" />
                    </div>

                    <div>
                        <h1>Tutor ATHENA</h1>
                        <div className="tutor-status">
                            <span className="status-dot"></span>
                            {loading ? "Escribiendo..." : "En línea"}
                        </div>
                    </div>
                </div>

                <button
                    type="button"
                    className={`history-toggle-btn ${
                        isHistoryOpen ? "history-toggle-btn--active" : ""
                    }`}
                    onClick={() => setIsHistoryOpen((prev) => !prev)}
                    title="Abrir historial de conversaciones"
                    aria-expanded={isHistoryOpen}
                >
                    <i className="fas fa-history"></i>
                    <span>Ver Historial</span>
                    {conversations.length > 0 && (
                        <span className="history-toggle-btn__badge">
                            {conversations.length}
                        </span>
                    )}
                </button>
            </section>

            {/* ÁREA PRINCIPAL DEL CHAT (ANCHO COMPLETO) */}
            <div className="tutor-workspace">
                <div className="tutor-chat-panel">
                    {/* ERROR BANNER SI EXISTE */}
                    {error && (
                        <div className="chat-error-banner">
                            <div>
                                <i className="fas fa-circle-exclamation"></i> {error}
                            </div>
                            <button onClick={() => setError("")} aria-label="Cerrar error">
                                <i className="fas fa-xmark"></i>
                            </button>
                        </div>
                    )}

                    {/* TIP DE ENFOQUE INICIAL Y ACCIONES RÁPIDAS (SOLO EN CHAT NUEVO) */}
                    {!activeConversationId && messages.length <= 1 && (
                        <>
                            <section className="ai-message">
                                <div className="ai-message__top">
                                    <div className="mini-avatar">
                                        <img src={Zorrito2} alt="ATHENA" />
                                    </div>

                                    <div>
                                        <h3>Hola, soy ATHENA</h3>
                                        <p>
                                            Puedo ayudarte con preguntas tipo ICFES, análisis de resultados y explicaciones paso a paso con Inteligencia Artificial.
                                        </p>
                                    </div>
                                </div>

                                <div className="tip-box">
                                    <i className="fas fa-bolt"></i>
                                    <span>
                                        Te recomiendo enfocarte en <strong>Lectura Crítica</strong> y <strong>Matemáticas</strong> esta semana.
                                    </span>
                                </div>
                            </section>

                            <section className="actions-grid">
                                <button
                                    className="action-card action-card--blue"
                                    onClick={() =>
                                        handleQuickAction(
                                            "Explícame la diferencia entre texto continuo y discontinuo en Lectura Crítica."
                                        )
                                    }
                                    type="button"
                                    disabled={loading}
                                >
                                    <i className="fas fa-book-open"></i>
                                    <span>Explicar un concepto</span>
                                </button>

                                <button
                                    className="action-card action-card--purple"
                                    onClick={() =>
                                        handleQuickAction(
                                            "Plantéame una pregunta tipo ICFES de Matemáticas sobre funciones cuadráticas."
                                        )
                                    }
                                    type="button"
                                    disabled={loading}
                                >
                                    <i className="fas fa-circle-question"></i>
                                    <span>Resolver una pregunta</span>
                                </button>

                                <button
                                    className="action-card action-card--green"
                                    onClick={() =>
                                        handleQuickAction(
                                            "¿Qué estrategias puedo usar para mejorar mi tiempo por pregunta en el examen ICFES?"
                                        )
                                    }
                                    type="button"
                                    disabled={loading}
                                >
                                    <i className="fas fa-chart-line"></i>
                                    <span>Consejos de rendimiento</span>
                                </button>

                                <button
                                    className="action-card action-card--yellow"
                                    onClick={() =>
                                        handleQuickAction(
                                            "Simulemos una sesión de práctica interactiva con preguntas cortas."
                                        )
                                    }
                                    type="button"
                                    disabled={loading}
                                >
                                    <i className="fas fa-bullseye"></i>
                                    <span>Practicar conmigo</span>
                                </button>
                            </section>
                        </>
                    )}

                    {/* HISTORIAL DE MENSAJES EN PANTALLA */}
                    <div className="chat-conversation">
                        {loadingConversation ? (
                            <div className="history-loading-state">
                                <i className="fas fa-circle-notch fa-spin"></i>
                                <span>Cargando mensajes de la conversación...</span>
                            </div>
                        ) : (
                            messages.map((msg) => {
                                const isUser = msg.sender === "user";
                                return (
                                    <div
                                        key={msg.id}
                                        className={`chat-bubble ${
                                            isUser ? "chat-bubble--user" : "chat-bubble--bot"
                                        }`}
                                    >
                                        {isUser ? (
                                            <div className="chat-bubble__user-avatar">
                                                <i className="fas fa-user"></i>
                                            </div>
                                        ) : (
                                            <div className="chat-bubble__avatar">
                                                <img src={Zorrito2} alt="ATHENA IA" />
                                            </div>
                                        )}

                                        <div className="chat-bubble__body">
                                            <div className="chat-bubble__content">
                                                <ChatMessage text={msg.text} isUser={isUser} />
                                            </div>
                                            {msg.time && (
                                                <span className="chat-bubble__time">
                                                    {msg.time}
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                );
                            })
                        )}

                        {loading && (
                            <div className="chat-typing">
                                <div className="chat-bubble__avatar">
                                    <img src={Zorrito2} alt="ATHENA IA" />
                                </div>
                                <div className="chat-typing__content">
                                    <span>ATHENA está escribiendo</span>
                                    <div className="chat-typing__dots">
                                        <span className="chat-typing__dot"></span>
                                        <span className="chat-typing__dot"></span>
                                        <span className="chat-typing__dot"></span>
                                    </div>
                                </div>
                            </div>
                        )}

                        <div ref={messagesEndRef} />
                    </div>

                    {/* INPUT DE CHAT */}
                    <section className="chat-input">
                        <input
                            ref={inputRef}
                            type="text"
                            placeholder="Escribe tu consulta académica o pregunta ICFES aquí..."
                            value={inputText}
                            onChange={(e) => setInputText(e.target.value)}
                            onKeyDown={handleKeyDown}
                            disabled={loading || loadingConversation}
                            autoFocus
                        />

                        <button
                            className="send-btn"
                            onClick={() => handleSendMessage()}
                            disabled={!inputText.trim() || loading || loadingConversation}
                            type="button"
                            title="Enviar mensaje"
                        >
                            <i className="fas fa-paper-plane"></i>
                        </button>
                    </section>

                    <p className="disclaimer">
                        ATHENA Tutor IA utiliza inteligencia artificial para potenciar tu preparación. Verifica siempre la información con tus materiales oficiales.
                    </p>
                </div>
            </div>

            {/* OVERLAY SEMITRANSPARENTE PARA CERRAR EL DRAWER AL HACER CLIC FUERA */}
            <div
                className={`history-overlay ${
                    isHistoryOpen ? "history-overlay--open" : ""
                }`}
                onClick={() => setIsHistoryOpen(false)}
                aria-hidden="true"
            />

            {/* OFF-CANVAS DRAWER DERECHO: HISTORIAL DE CHATS */}
            <aside
                className={`history-sidebar ${
                    isHistoryOpen ? "history-sidebar--open" : ""
                }`}
                aria-label="Historial de Chats"
            >
                <div className="history-sidebar__header">
                    <div className="history-sidebar__top-row">
                        <div className="history-sidebar__title">
                            <i className="fas fa-clock-rotate-left"></i>
                            <h2>Historial de Chats</h2>
                        </div>

                        <button
                            type="button"
                            className="history-close-btn"
                            onClick={() => setIsHistoryOpen(false)}
                            title="Cerrar historial"
                            aria-label="Cerrar historial"
                        >
                            <i className="fas fa-times"></i>
                        </button>
                    </div>

                    <button
                        type="button"
                        className="new-chat-btn"
                        onClick={handleNewChat}
                        disabled={loading || loadingConversation}
                    >
                        <i className="fas fa-plus"></i>
                        <span>Nuevo Chat</span>
                    </button>
                </div>

                <div className="history-list">
                    {loadingHistory && conversations.length === 0 ? (
                        <div className="history-empty">
                            <i className="fas fa-circle-notch fa-spin"></i>
                            <p>Cargando historial...</p>
                        </div>
                    ) : conversations.length === 0 ? (
                        <div className="history-empty">
                            <i className="far fa-comments"></i>
                            <p>Aún no tienes conversaciones guardadas.</p>
                        </div>
                    ) : (
                        conversations.map((conv) => {
                            const convId = String(conv._id || conv.id);
                            const isActive =
                                String(activeConversationId || "") === convId;

                            return (
                                <button
                                    key={convId}
                                    type="button"
                                    className={`history-item ${
                                        isActive ? "history-item--active" : ""
                                    }`}
                                    onClick={() => loadConversation(convId)}
                                >
                                    <div className="history-item__icon">
                                        <i className="far fa-message"></i>
                                    </div>
                                    <div className="history-item__info">
                                        <span className="history-item__title">
                                            {conv.title || "Conversación"}
                                        </span>
                                        {conv.updated_at && (
                                            <span className="history-item__date">
                                                {formatHistoryDate(conv.updated_at)}
                                            </span>
                                        )}
                                    </div>
                                </button>
                            );
                        })
                    )}
                </div>
            </aside>
        </div>
    );
};

export default Chat;
