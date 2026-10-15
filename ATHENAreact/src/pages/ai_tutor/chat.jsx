import "./chat.css";
import { useState, useRef, useEffect } from "react";
import Zorrito from "../../assets/images/Zorrito6.png";
import Zorrito2 from "../../assets/images/Zorrito_3.png";
import { sendMessage } from "../../api/ai";

const Chat = () => {
    const [messages, setMessages] = useState([
        {
            id: 1,
            sender: "bot",
            text: "¡Hola! Soy ATHENA, tu tutora académica con Inteligencia Artificial. Puedo ayudarte a resolver dudas, analizar tus resultados y explicarte preguntas oficiales tipo ICFES paso a paso. ¿Qué tema te gustaría repasar hoy?",
            time: new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }),
        },
    ]);
    const [inputText, setInputText] = useState("");
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState("");
    const [conversationId, setConversationId] = useState(null);

    const messagesEndRef = useRef(null);
    const inputRef = useRef(null);

    // Auto-scroll al final del chat
    const scrollToBottom = () => {
        messagesEndRef.current?.scrollIntoView({ behavior: "smooth" });
    };

    useEffect(() => {
        scrollToBottom();
    }, [messages, loading]);

    // Función para procesar y enviar mensaje
    const handleSendMessage = async (textToSend = inputText) => {
        const text = (typeof textToSend === "string" ? textToSend : inputText).trim();
        if (!text || loading) return;

        const userMsg = {
            id: Date.now(),
            sender: "user",
            text: text,
            time: new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }),
        };

        // Agregar mensaje del usuario inmediatamente al historial visible
        setMessages((prev) => [...prev, userMsg]);
        setInputText("");
        setError("");
        setLoading(true);

        try {
            // Llamar a la API de Laravel (Gemini) usando el servicio Axios y enviando el conversation_id si existe
            const response = await sendMessage(text, conversationId);
            console.log("Respuesta de ATHENA IA:", response);

            // Almacenar identificador de conversación si el backend lo retorna
            if (response?.data?.conversation_id) {
                setConversationId(response.data.conversation_id);
            }

            // Normalizar la lectura de la respuesta real de Gemini
            // Prioridad absoluta a data.ai_response (formato devuelto por AiService / AIController)
            // Se excluye estrictamente response.message (mensaje de estado HTTP) y response.data.message (pregunta del usuario)
            const botReply =
                response?.data?.ai_response ||
                response?.ai_response ||
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
        } catch (err) {
            console.error("Error al comunicarse con ATHENA IA:", err);

            let errorMsg = "Lo siento, ocurrió un problema al conectar con el servidor. Por favor intenta nuevamente.";

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

    // Manejador del Enter
    const handleKeyDown = (e) => {
        if (e.key === "Enter" && !e.shiftKey) {
            e.preventDefault();
            handleSendMessage();
        }
    };

    // Click en acciones rápidas
    const handleQuickAction = (promptText) => {
        if (loading) return;
        handleSendMessage(promptText);
    };

    // Reiniciar conversación
    const handleResetChat = () => {
        if (loading) return;
        setConversationId(null);
        setMessages([
            {
                id: Date.now(),
                sender: "bot",
                text: "Conversación reiniciada. ¿En qué puedo orientarte hoy para tus pruebas ICFES Saber 11?",
                time: new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }),
            },
        ]);
        setError("");
    };

    return (
        <div className="main__content">
            {/* HEADER */}
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
                    className="refresh-btn"
                    title="Reiniciar conversación"
                    onClick={handleResetChat}
                    type="button"
                    disabled={loading}
                >
                    <i className="fas fa-rotate-right"></i>
                </button>
            </section>

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

            {/* TIP DE ENFOQUE INICIAL */}
            {messages.length <= 1 && (
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

                    {/* ACCIONES RÁPIDAS */}
                    <section className="actions-grid">
                        <button
                            className="action-card action-card--blue"
                            onClick={() => handleQuickAction("Explícame la diferencia entre texto continuo y discontinuo en Lectura Crítica.")}
                            type="button"
                            disabled={loading}
                        >
                            <i className="fas fa-book-open"></i>
                            <span>Explicar un concepto</span>
                        </button>

                        <button
                            className="action-card action-card--purple"
                            onClick={() => handleQuickAction("Plantéame una pregunta tipo ICFES de Matemáticas sobre funciones cuadráticas.")}
                            type="button"
                            disabled={loading}
                        >
                            <i className="fas fa-circle-question"></i>
                            <span>Resolver una pregunta</span>
                        </button>

                        <button
                            className="action-card action-card--green"
                            onClick={() => handleQuickAction("¿Qué estrategias puedo usar para mejorar mi tiempo por pregunta en el examen ICFES?")}
                            type="button"
                            disabled={loading}
                        >
                            <i className="fas fa-chart-line"></i>
                            <span>Consejos de rendimiento</span>
                        </button>

                        <button
                            className="action-card action-card--yellow"
                            onClick={() => handleQuickAction("Simulemos una sesión de práctica interactiva con preguntas cortas.")}
                            type="button"
                            disabled={loading}
                        >
                            <i className="fas fa-bullseye"></i>
                            <span>Practicar conmigo</span>
                        </button>
                    </section>
                </>
            )}

            {/* HISTORIAL DE MENSAJES */}
            <div className="chat-conversation">
                {messages.map((msg) => {
                    const isUser = msg.sender === "user";
                    return (
                        <div
                            key={msg.id}
                            className={`chat-bubble ${isUser ? "chat-bubble--user" : "chat-bubble--bot"}`}
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
                                <div className="chat-bubble__content">{msg.text}</div>
                                <span className="chat-bubble__time">{msg.time}</span>
                            </div>
                        </div>
                    );
                })}

                {/* INDICADOR DE CARGA (ATHENA está escribiendo...) */}
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
                    disabled={loading}
                    autoFocus
                />

                <button
                    className="send-btn"
                    onClick={() => handleSendMessage()}
                    disabled={!inputText.trim() || loading}
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
    );
};

export default Chat;
