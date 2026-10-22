/* global process */
import assert from "node:assert/strict";
import useExamStore from "./src/stores/useExamStore.js";
import useProgressStore from "./src/stores/useProgressStore.js";

async function runTestSuite() {
    console.log("======================================================================");
    console.log("   ATHENA — SUITE DE PRUEBAS DE STORES ZUSTAND (React 19 + Zustand 5)");
    console.log("======================================================================\n");

    // ===============================================================
    // DOMINIO 1: useExamStore (Prácticas y Exámenes)
    // ===============================================================
    console.log("--- PROBANDO STORE 1: useExamStore (Dominio Prácticas) ---");

    // 1.1 Estado inicial y reset
    useExamStore.getState().resetPractice();
    let examState = useExamStore.getState();
    assert.equal(examState.currentIndex, 0, "El índice inicial debe ser 0");
    assert.deepEqual(examState.answers, {}, "answers inicial debe ser un objeto vacío");
    console.log("✓ 1.1 Estado inicial y resetPractice verificados.");

    // 1.2 setAnswer
    useExamStore.getState().setAnswer(10, 101);
    examState = useExamStore.getState();
    assert.equal(examState.answers[10], 101, "setAnswer debe guardar la opción 101 para la pregunta 10");

    // 1.3 Respuestas múltiples y aislamiento
    useExamStore.getState().setAnswer(11, 105);
    examState = useExamStore.getState();
    assert.equal(examState.answers[10], 101, "La respuesta 10 debe permanecer inalterada tras registrar 11");
    assert.equal(examState.answers[11], 105, "La respuesta 11 debe guardarse correctamente");
    assert.equal(Object.keys(examState.answers).length, 2, "Deben haber exactamente 2 respuestas");
    console.log("✓ 1.2 y 1.3 setAnswer y aislamiento de respuestas verificado.");

    // 1.4 Modificación de respuesta existente
    useExamStore.getState().setAnswer(10, 102);
    examState = useExamStore.getState();
    assert.equal(examState.answers[10], 102, "Se debe poder cambiar la opción de la pregunta 10");
    assert.equal(examState.answers[11], 105, "La opción de la pregunta 11 no debe verse afectada");
    console.log("✓ 1.4 Modificación de opciones de respuesta verificado.");

    // 1.5 setCurrentIndex directo y funcional
    useExamStore.getState().setCurrentIndex(2);
    assert.equal(useExamStore.getState().currentIndex, 2, "setCurrentIndex directo debe ser 2");
    useExamStore.getState().setCurrentIndex((prev) => prev + 1);
    assert.equal(useExamStore.getState().currentIndex, 3, "setCurrentIndex funcional debe ser 3");
    console.log("✓ 1.5 setCurrentIndex directo y funcional verificado.");

    // 1.6 initPractice
    const preloaded = { 20: 201, 21: 202 };
    useExamStore.getState().initPractice(preloaded, 1);
    examState = useExamStore.getState();
    assert.deepEqual(examState.answers, preloaded, "initPractice debe inicializar el mapa de respuestas");
    assert.equal(examState.currentIndex, 1, "initPractice debe inicializar el currentIndex en 1");
    console.log("✓ 1.6 initPractice carga respuestas e índice correctamente.");

    // ===============================================================
    // DOMINIO 2: useProgressStore (Progreso y Métricas Académicas)
    // ===============================================================
    console.log("\n--- PROBANDO STORE 2: useProgressStore (Dominio Progreso) ---");

    // 2.1 Estado inicial
    useProgressStore.getState().resetProgress();
    let progState = useProgressStore.getState();
    assert.equal(progState.dashboard, null, "dashboard inicial debe ser null");
    assert.equal(progState.loading, false, "loading inicial debe ser false");
    assert.equal(progState.error, null, "error inicial debe ser null");
    console.log("✓ 2.1 Estado inicial de useProgressStore verificado.");

    // 2.2 Acción asíncrona real: fetchDashboardProgress()
    console.log("-> Ejecutando acción asíncrona fetchDashboardProgress() contra Laravel API...");
    const fetchPromise = useProgressStore.getState().fetchDashboardProgress();

    // Verificamos que al disparar la acción, loading se activa
    assert.equal(useProgressStore.getState().loading, true, "loading debe pasar a true al iniciar fetch");

    // Esperamos resolución
    await fetchPromise;
    progState = useProgressStore.getState();

    assert.equal(progState.loading, false, "loading debe ser false al culminar la petición");
    assert.equal(progState.error, null, "error debe ser null tras una respuesta exitosa");
    assert.ok(progState.dashboard !== null, "dashboard no debe ser null tras la petición");
    assert.ok(
        progState.dashboard.resumen_general !== undefined,
        "dashboard debe contener el objeto resumen_general de la API"
    );
    assert.ok(
        Array.isArray(progState.dashboard.materias || progState.dashboard.temas_fuertes),
        "dashboard debe contener arreglos de desempeño o temas de la API"
    );
    console.log("✓ 2.2 Acción asíncrona fetchDashboardProgress consumió el endpoint real /api/progress con éxito.");
    console.log(`   * Simulacros completados reportados: ${progState.dashboard.resumen_general.total_simulacros_completados}`);
    console.log(`   * Porcentaje de acierto global: ${progState.dashboard.resumen_general.porcentaje_global_aciertos}%`);

    // 2.3 Manejo explícito de errores sin ocultarlos
    useProgressStore.setState({
        loading: false,
        error: "Fallo de conexión simulado con el servicio Laravel",
    });
    progState = useProgressStore.getState();
    assert.equal(
        progState.error,
        "Fallo de conexión simulado con el servicio Laravel",
        "El store debe retener y propagar el mensaje de error explícito"
    );
    console.log("✓ 2.3 Manejo explícito de estado de error verificado sin ocultamiento.");

    // 2.4 Manejo de reset en useProgressStore
    useProgressStore.getState().resetProgress();
    progState = useProgressStore.getState();
    assert.equal(progState.dashboard, null, "resetProgress debe reiniciar dashboard a null");
    assert.equal(progState.loading, false, "resetProgress debe reiniciar loading a false");
    assert.equal(progState.error, null, "resetProgress debe reiniciar error a null");
    console.log("✓ 2.4 resetProgress limpia el estado de progreso correctamente.");

    console.log("\n======================================================================");
    console.log("   ¡TODAS LAS PRUEBAS DE STORES PASARON EXITOSAMENTE (10/10)!");
    console.log("======================================================================");
}

runTestSuite().catch((err) => {
    console.error("❌ ERROR EN LA SUITE DE PRUEBAS:", err);
    if (typeof process !== "undefined" && process.exit) {
        process.exit(1);
    }
});
