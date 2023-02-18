import React from "react";
import PropTypes from "prop-types";

const Login = ( props ) => (
    <nav className="login">
        <h2>Inventory Login</h2>
        <p>sign in to manage your store's inventory.</p>
        <button className="github" onClick={() => props.authenticate('Github')}>
            Login with Github
        </button>
        {/* twitter and facebook logins not working
        <button className="twitter" onClick={() => props.authenticate('Twitter')}>
            Login with Twitter
        </button>
        <button className="facebook" onClick={() => props.authenticate('Facebook')}>
            Login with Facebook
        </button>*/}
    </nav>
);

Login.propTypes = {
 authenticate: PropTypes.func.isRequired
}
 export default Login;