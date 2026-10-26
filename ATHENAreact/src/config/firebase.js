import { initializeApp } from "firebase/app";
import { getAuth } from "firebase/auth"; 

const firebaseConfig = {
    apiKey: "AIzaSyBC6VS5NFvXDzvrqi9iIHcd1mX9EJ2xRO8",
    authDomain: "athena-595d2.firebaseapp.com",
    projectId: "athena-595d2",
    storageBucket: "athena-595d2.firebasestorage.app",
    messagingSenderId: "818925413318",
    appId: "1:818925413318:web:380e856acff8bf451b48c7"
};

const app = initializeApp(firebaseConfig);
export const auth = getAuth(app); 
export default app;