import assert from "node:assert/strict";
import useExamStore from "./src/stores/useExamStore.js";

console.log("=== INICIANDO PRUEBAS UNITARIAS DE ZUSTAND (useExamStore) ===");

// 1. Estado inicial
useExamStore.getState().resetPractice();
const initial = useExamStore.getState();
assert.equal(initial.currentIndex, 0, "El índice inicial debe ser 0");
assert.deepEqual(initial.answers, {}, "answers inicial debe ser un objeto vacío");
console.log("✓ Estado inicial verificado correctamente.");

// 2. setAnswer actualiza answers
useExamStore.getState().setAnswer(101, 501);
const afterFirstAnswer = useExamStore.getState();
assert.equal(afterFirstAnswer.answers[101], 501, "setAnswer debe guardar optionId 501 en questionId 101");
console.log("✓ setAnswer actualiza answers correctamente.");

// 3. Respuesta de una pregunta NO borra la de otra
useExamStore.getState().setAnswer(102, 506);
const afterSecondAnswer = useExamStore.getState();
assert.equal(afterSecondAnswer.answers[101], 501, "La respuesta de 101 debe mantenerse tras responder 102");
assert.equal(afterSecondAnswer.answers[102], 506, "La respuesta de 102 debe agregarse correctamente");
assert.equal(Object.keys(afterSecondAnswer.answers).length, 2, "Deben existir exactamente 2 respuestas guardadas");
console.log("✓ La respuesta de una pregunta no sobrescribe la de otra.");

// 4. Modificar respuesta de una pregunta existente
useExamStore.getState().setAnswer(101, 502);
const afterUpdateAnswer = useExamStore.getState();
assert.equal(afterUpdateAnswer.answers[101], 502, "Se debe poder cambiar la opción de una pregunta");
assert.equal(afterUpdateAnswer.answers[102], 506, "La respuesta de 102 no debe alterarse al cambiar 101");
console.log("✓ La modificación de una opción en una pregunta no altera las demás.");

// 5. setCurrentIndex cambia la pregunta actual
useExamStore.getState().setCurrentIndex(3);
assert.equal(useExamStore.getState().currentIndex, 3, "setCurrentIndex debe cambiar currentIndex a 3");
useExamStore.getState().setCurrentIndex((prev) => prev + 1);
assert.equal(useExamStore.getState().currentIndex, 4, "setCurrentIndex con función debe incrementar a 4");
console.log("✓ setCurrentIndex cambia la pregunta actual con valores directos y funciones.");

// 6. resetPractice limpia el estado
useExamStore.getState().resetPractice();
const afterReset = useExamStore.getState();
assert.equal(afterReset.currentIndex, 0, "resetPractice debe restablecer currentIndex a 0");
assert.deepEqual(afterReset.answers, {}, "resetPractice debe vaciar answers");
console.log("✓ resetPractice limpia el estado completamente.");

// 7. initPractice carga respuestas iniciales
const preloaded = { 201: 601, 202: 604, 203: 607 };
useExamStore.getState().initPractice(preloaded, 2);
const afterInit = useExamStore.getState();
assert.deepEqual(afterInit.answers, preloaded, "initPractice debe cargar el mapa completo de respuestas previas");
assert.equal(afterInit.currentIndex, 2, "initPractice debe establecer el índice indicado");
console.log("✓ initPractice inicializa las respuestas previas y el índice correctamente.");

console.log("=== TODAS LAS PRUEBAS DE useExamStore PASARON EXITOSAMENTE (7/7) ===");
