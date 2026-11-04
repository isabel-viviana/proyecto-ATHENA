import axios from 'axios';

/**
 * Cliente Axios independiente para el microservicio ATHENA Question Bank.
 * Se conecta a Laravel Question Bank (puerto local 8001).
 * Mantiene total aislamiento del cliente principal (ATHENA Core, puerto 8000).
 */
const questionBankApi = axios.create({
    baseURL: import.meta.env.VITE_QUESTION_BANK_API_URL || 'http://127.0.0.1:8001/api/v1',
    headers: {
        'Accept': 'application/json',
    },
});

export default questionBankApi;

