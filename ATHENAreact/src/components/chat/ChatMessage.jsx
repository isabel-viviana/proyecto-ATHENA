import React from "react";
import ReactMarkdown from "react-markdown";
import remarkGfm from "remark-gfm";
import remarkMath from "remark-math";
import rehypeKatex from "rehype-katex";
import "katex/dist/katex.min.css";

const ChatMessage = ({ text, isUser = false }) => {
    if (isUser) {
        return <div className="md-user-text">{text}</div>;
    }

    return (
        <div className="md-container">
            <ReactMarkdown
                remarkPlugins={[remarkGfm, remarkMath]}
                rehypePlugins={[rehypeKatex]}
                components={{
                    h1: ({ children }) => (
                        <h1 className="md-title md-title--h1">{children}</h1>
                    ),
                    h2: ({ children }) => (
                        <h2 className="md-title md-title--h2">{children}</h2>
                    ),
                    h3: ({ children }) => (
                        <h3 className="md-title md-title--h3">{children}</h3>
                    ),
                    p: ({ children }) => (
                        <p className="md-paragraph">{children}</p>
                    ),
                    strong: ({ children }) => (
                        <strong className="md-strong">{children}</strong>
                    ),
                    em: ({ children }) => (
                        <em className="md-italic">{children}</em>
                    ),
                    ul: ({ children }) => (
                        <ul className="md-list md-list--ul">{children}</ul>
                    ),
                    ol: ({ children }) => (
                        <ol className="md-list md-list--ol">{children}</ol>
                    ),
                    li: ({ children }) => (
                        <li className="md-list-item">{children}</li>
                    ),
                    blockquote: ({ children }) => (
                        <blockquote className="md-blockquote">
                            {children}
                        </blockquote>
                    ),
                    code: ({ inline, className, children, ...props }) => {
                        const isBlock = !inline && /language-(\w+)/.test(className || "");
                        if (isBlock || (!inline && String(children).includes("\n"))) {
                            return (
                                <pre className="md-code-block">
                                    <code className={className} {...props}>
                                        {children}
                                    </code>
                                </pre>
                            );
                        }
                        return (
                            <code className="md-code-inline" {...props}>
                                {children}
                            </code>
                        );
                    },
                    table: ({ children }) => (
                        <div className="md-table-wrapper">
                            <table className="md-table">{children}</table>
                        </div>
                    ),
                    thead: ({ children }) => (
                        <thead className="md-thead">{children}</thead>
                    ),
                    tbody: ({ children }) => (
                        <tbody className="md-tbody">{children}</tbody>
                    ),
                    tr: ({ children }) => (
                        <tr className="md-tr">{children}</tr>
                    ),
                    th: ({ children }) => (
                        <th className="md-th">{children}</th>
                    ),
                    td: ({ children }) => (
                        <td className="md-td">{children}</td>
                    ),
                    hr: () => <hr className="md-divider" />,
                }}
            >
                {text}
            </ReactMarkdown>
        </div>
    );
};

export default ChatMessage;

