import api from "./axios";

export const sendMessage = async (data, conversationId = null) => {
    let payload;
    if (typeof data === "string") {
        payload = {
            message: data,
            ...(conversationId ? { conversation_id: conversationId } : {}),
        };
    } else if (typeof data === "object" && data !== null) {
        payload = {
            message: data.message || data.prompt || "",
            ...(data.conversation_id || conversationId
                ? { conversation_id: data.conversation_id || conversationId }
                : {}),
            ...data,
        };
    } else {
        payload = { message: "" };
    }

    const response = await api.post("/ai/message", payload);
    return response.data;
};
