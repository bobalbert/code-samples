import Rebase from "re-base";
import firebase from "firebase";

const firebaseApp = firebase.initializeApp({
    // Your web app's Firebase configuration
    apiKey: "AIzaSyCkz79tZUNXStw4ukrkGkBMhtYMvJ6330M",
    authDomain: "catch-of-the-day-balbert.firebaseapp.com",
    databaseURL: "https://catch-of-the-day-balbert-default-rtdb.firebaseio.com"
});

const base = Rebase.createClass( firebaseApp.database() );

// this is a name export
export { firebaseApp}

// this is a default export
export default base;