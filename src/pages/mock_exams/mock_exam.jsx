import "./mock_exam.css";
import { useEffect, useState } from "react";
import { useSearchParams, useNavigate } from "react-router-dom";

import Zorrito from "../../assets/images/Zorrito2D.png";

import {
    getExamQuestions,
    submitAnswer,
    finishExam,
} from "../../api/exams";

const MockExam = () => {
    const [searchParams] = useSearchParams();
    const navigate = useNavigate();

    // ID del intento creado anteriormente
    const examId = searchParams.get("id");

    const [questions, setQuestions] = useState([]);
    const [currentQuestion, setCurrentQuestion] = useState(0);

    const [answers, setAnswers] = useState({});

    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [finishing, setFinishing] = useState(false);

    const [error, setError] = useState("");

    // 4 horas y 30 minutos
    const [timeLeft, setTimeLeft] = useState(4 * 60 * 60 + 30 * 60);

    /*
     * ============================================================
     * CARGAR PREGUNTAS
     * ============================================================
     */

    useEffect(() => {
        const loadQuestions = async () => {
            if (!examId) {
                setError(
                    "No encontramos el ID del simulacro. Regresa a la configuración e inténtalo nuevamente."
                );

                setLoading(false);
                return;
            }

            try {
                setLoading(true);
                setError("");

                const response = await getExamQuestions(examId);

                console.log("PREGUNTAS DEL SIMULACRO:", response);

                /*
                 * Soportamos diferentes estructuras posibles
                 * de la respuesta Laravel.
                 */
                const receivedQuestions =
                    response?.data?.questions ||
                    response?.questions ||
                    response?.data ||
                    [];

                if (!Array.isArray(receivedQuestions)) {
                    throw new Error(
                        "El servidor no devolvió una lista válida de preguntas."
                    );
                }

                if (receivedQuestions.length === 0) {
                    throw new Error(
                        "Este simulacro no tiene preguntas disponibles."
                    );
                }

                setQuestions(receivedQuestions);
            } catch (err) {
                console.error(
                    "ERROR AL CARGAR PREGUNTAS:",
                    err
                );

                setError(
                    err?.response?.data?.message ||
                        err?.message ||
                        "No se pudieron cargar las preguntas."
                );
            } finally {
                setLoading(false);
            }
        };

        loadQuestions();
    }, [examId]);

    /*
     * ============================================================
     * TEMPORIZADOR
     * ============================================================
     */

    useEffect(() => {
        if (loading || questions.length === 0) {
            return;
        }

        const timer = setInterval(() => {
            setTimeLeft((previous) => {
                if (previous <= 1) {
                    clearInterval(timer);
                    return 0;
                }

                return previous - 1;
            });
        }, 1000);

        return () => clearInterval(timer);
    }, [loading, questions.length, timeLeft]);

    /*
     * ============================================================
     * FORMATEAR TIEMPO
     * ============================================================
     */

    const formatTime = (seconds) => {
        const hours = Math.floor(seconds / 3600);

        const minutes = Math.floor(
            (seconds % 3600) / 60
        );

        const remainingSeconds = seconds % 60;

        return `${String(hours).padStart(2, "0")}:${String(
            minutes
        ).padStart(2, "0")}:${String(
            remainingSeconds
        ).padStart(2, "0")}`;
    };

    /*
     * ============================================================
     * PREGUNTA ACTUAL
     * ============================================================
     */

    const question = questions[currentQuestion];

    /*
     * ============================================================
     * OBTENER TEXTO DE LA PREGUNTA
     * ============================================================
     */

    const getQuestionText = (question) => {
        return (
            question?.question ||
            question?.question_text ||
            question?.statement ||
            question?.text ||
            question?.enunciado ||
            "Pregunta sin texto"
        );
    };

    /*
     * ============================================================
     * OBTENER OPCIONES
     * ============================================================
     */

    const getOptions = (question) => {
        return (
            question?.options ||
            question?.question_options ||
            question?.answers ||
            []
        );
    };

    /*
     * ============================================================
     * OBTENER TEXTO DE UNA OPCIÓN
     * ============================================================
     */

    const getOptionText = (option) => {
        return (
            option?.text ||
            option?.option_text ||
            option?.content ||
            option?.label ||
            option?.description ||
            ""
        );
    };

    /*
     * ============================================================
     * LETRA DE LA OPCIÓN
     * ============================================================
     */

    const getOptionLetter = (index) => {
        return String.fromCharCode(65 + index);
    };

    /*
     * ============================================================
     * SELECCIONAR RESPUESTA
     * ============================================================
     */

    const handleSelectAnswer = async (option) => {
        if (!question || saving || finishing) {
            return;
        }

        const questionId = question.id;
        const optionId = option.id;

        if (!questionId || !optionId) {
            setError(
                "La pregunta o la opción no tiene un ID válido."
            );

            return;
        }

        // Guardamos visualmente la respuesta
        setAnswers((previous) => ({
            ...previous,
            [questionId]: optionId,
        }));

        try {
            setSaving(true);
            setError("");

            /*
             * Enviamos la respuesta a Laravel.
             *
             * El backend determina si es correcta.
             * React NO recibe ni utiliza is_correct.
             */
            const response = await submitAnswer(examId, {
                question_id: questionId,
                selected_option_id: optionId,
                time_spent_seconds: 0,
            });

            console.log(
                "RESPUESTA GUARDADA:",
                response
            );
        } catch (err) {
            console.error(
                "ERROR AL GUARDAR RESPUESTA:",
                err
            );

            setError(
                err?.response?.data?.message ||
                    err?.message ||
                    "No se pudo guardar la respuesta."
            );
        } finally {
            setSaving(false);
        }
    };

    /*
     * ============================================================
     * SIGUIENTE PREGUNTA
     * ============================================================
     */

    const handleNext = () => {
        if (currentQuestion < questions.length - 1) {
            setCurrentQuestion(
                (previous) => previous + 1
            );

            window.scrollTo({
                top: 0,
                behavior: "smooth",
            });
        }
    };

    /*
     * ============================================================
     * PREGUNTA ANTERIOR
     * ============================================================
     */

    const handlePrevious = () => {
        if (currentQuestion > 0) {
            setCurrentQuestion(
                (previous) => previous - 1
            );

            window.scrollTo({
                top: 0,
                behavior: "smooth",
            });
        }
    };

    /*
     * ============================================================
     * FINALIZAR SIMULACRO
     * ============================================================
     */

    const handleFinishExam = async () => {
        if (!examId || finishing) {
            return;
        }

        try {
            setFinishing(true);
            setError("");

            /*
             * Tiempo utilizado.
             *
             * 4h30 = 16200 segundos
             */
            const totalExamTime = 4 * 60 * 60 + 30 * 60;

            const timeSpent =
                totalExamTime - timeLeft;

            const response = await finishExam(
                examId,
                {
                    time_spent_seconds:
                        timeSpent,
                }
            );

            console.log(
                "SIMULACRO FINALIZADO:",
                response
            );

            /*
             * Enviamos el ID del intento a resultados.
             */
            navigate(
                `/mock/result?id=${examId}`
            );
        } catch (err) {
            console.error(
                "ERROR AL FINALIZAR SIMULACRO:",
                err
            );

            setError(
                err?.response?.data?.message ||
                    err?.message ||
                    "No se pudo finalizar el simulacro."
            );

            setFinishing(false);
        }
    };

    /*
     * ============================================================
     * CARGANDO
     * ============================================================
     */

    if (loading) {
        return (
            <div className="main__content">
                <section className="question">
                    <h1 className="question__title">
                        Cargando simulacro...
                    </h1>

                    <p>
                        Estamos preparando tus preguntas.
                    </p>
                </section>
            </div>
        );
    }

    /*
     * ============================================================
     * ERROR
     * ============================================================
     */

    if (error && questions.length === 0) {
        return (
            <div className="main__content">
                <section className="question">
                    <h1 className="question__title">
                        No pudimos cargar el simulacro
                    </h1>

                    <p>{error}</p>

                    <button
                        className="footer-btn footer-btn--primary"
                        onClick={() =>
                            navigate("/mock/config")
                        }
                    >
                        Volver a configuración
                    </button>
                </section>
            </div>
        );
    }

    /*
     * ============================================================
     * SI NO HAY PREGUNTA
     * ============================================================
     */

    if (!question) {
        return (
            <div className="main__content">
                <section className="question">
                    <h1 className="question__title">
                        No hay preguntas disponibles.
                    </h1>
                </section>
            </div>
        );
    }

    const options = getOptions(question);

    const selectedOptionId =
        answers[question.id];

    const isLastQuestion =
        currentQuestion === questions.length - 1;

    const progressPercentage =
        ((currentQuestion + 1) /
            questions.length) *
        100;

    /*
     * ============================================================
     * INTERFAZ
     * ============================================================
     */

    return (
        <>
            <div className="main__content">

                {/* TOP */}
                <section className="quiz-top">

                    <div className="quiz-top__left">

                        <span className="quiz-top__subject">
                            {question?.subject?.name ||
                                question?.subject_name ||
                                question?.subject ||
                                "SIMULACRO"}
                        </span>

                        <h2>
                            Pregunta{" "}
                            {currentQuestion + 1} de{" "}
                            {questions.length}
                        </h2>

                    </div>

                    <div className="quiz-top__timer">

                        <i className="fa-regular fa-clock"></i>

                        <span>
                            {formatTime(timeLeft)}
                        </span>

                    </div>

                </section>

                {/* PROGRESS */}
                <div className="progress">

                    <div
                        className="progress__fill"
                        style={{
                            width: `${progressPercentage}%`,
                        }}
                    ></div>

                </div>

                {/* ERROR DE GUARDADO */}
                {error && (
                    <div className="error-message">
                        {error}
                    </div>
                )}

                {/* QUESTION */}
                <section className="question">

                    <h1 className="question__title">
                        {getQuestionText(question)}
                    </h1>

                    {/* OPTIONS */}
                    <div className="options">

                        {options.map(
                            (option, index) => {

                                const optionId =
                                    option.id;

                                const isSelected =
                                    selectedOptionId ===
                                    optionId;

                                return (
                                    <button
                                        key={optionId}
                                        type="button"
                                        className={`option ${
                                            isSelected
                                                ? "option--selected"
                                                : ""
                                        }`}
                                        onClick={() =>
                                            handleSelectAnswer(
                                                option
                                            )
                                        }
                                        disabled={
                                            saving ||
                                            finishing
                                        }
                                    >

                                        <span className="option__letter">
                                            {getOptionLetter(
                                                index
                                            )}
                                        </span>

                                        <span>
                                            {getOptionText(
                                                option
                                            )}
                                        </span>

                                    </button>
                                );
                            }
                        )}

                    </div>

                    {saving && (
                        <p>
                            Guardando respuesta...
                        </p>
                    )}

                </section>

                {/* FOOTER */}
                <section className="quiz-footer">

                    <button
                        type="button"
                        className="footer-btn footer-btn--ghost"
                        onClick={handlePrevious}
                        disabled={
                            currentQuestion === 0 ||
                            saving ||
                            finishing
                        }
                    >
                        <i className="fas fa-chevron-left"></i>

                        Anterior
                    </button>

                    {!isLastQuestion ? (

                        <button
                            type="button"
                            className="footer-btn footer-btn--primary"
                            onClick={handleNext}
                            disabled={
                                saving ||
                                finishing
                            }
                        >
                            Siguiente

                            <i className="fas fa-chevron-right"></i>
                        </button>

                    ) : (

                        <button
                            type="button"
                            className="footer-btn footer-btn--primary"
                            onClick={handleFinishExam}
                            disabled={
                                saving ||
                                finishing
                            }
                        >
                            {finishing
                                ? "Finalizando..."
                                : "Finalizar"}

                            {!finishing && (
                                <i className="fas fa-check"></i>
                            )}
                        </button>

                    )}

                </section>

            </div>

            {/* ASSISTANT */}
            <div className="assistant">

                <div className="assistant__message">

                    {saving
                        ? "Guardando tu respuesta..."
                        : isLastQuestion
                        ? "¡Última pregunta! Revisa tu respuesta antes de finalizar."
                        : "¡Concéntrate! Tú puedes con este simulacro."}

                </div>

                <div className="assistant__bot">

                    <img
                        src={Zorrito}
                        alt="Zorrito"
                    />

                </div>

            </div>
        </>
    );
};

export default MockExam;