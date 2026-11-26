import api from "./axios";

/**
 * Envía el mensaje del usuario junto con el conversation_id activo (si existe).
 */
export const sendMessage = async (message, conversationId = null) => {
    const text =
        typeof message === "string"
            ? message
            : message?.message || message?.prompt || "";

    const resolvedConversationId =
        conversationId ||
        (typeof message === "object" ? message?.conversation_id : null) ||
        null;

    const payload = {
        message: text,
        ...(resolvedConversationId
            ? { conversation_id: String(resolvedConversationId) }
            : {}),
    };

    const response = await api.post("/ai/message", payload);
    const body = response.data || {};
    const inner = body.data || {};

    return {
        ...body,
        conversation_id:
            body.conversation_id ||
            inner.conversation_id ||
            inner._id ||
            inner.id ||
            null,
        ai_response:
            body.ai_response ||
            inner.ai_response ||
            inner.reply ||
            body.reply ||
            null,
        data: inner,
    };
};

/**
 * Obtiene la lista de conversaciones del usuario (_id, title, updated_at).
 */
export const getConversations = async () => {
    const response = await api.get("/ai/conversations");
    return response.data;
};

/**
 * Obtiene una conversación específica por su _id con todo su array de messages.
 */
export const getConversation = async (id) => {
    const response = await api.get(`/ai/conversations/${id}`);
    return response.data;
};
