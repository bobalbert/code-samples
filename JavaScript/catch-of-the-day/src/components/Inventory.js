import React from "react";
import PropTypes from "prop-types";
import firebase from "firebase";
import AddFishForm from "./AddFishForm";
import EditFishForm from "./EditFishForm";
import Login from "./login";
import base, { firebaseApp } from "../base";


class Inventory extends React.Component {
    static propTypes = {
        fishes: PropTypes.object,
        updatefish: PropTypes.func,
        deleteFish: PropTypes.func,
        loadSampleFishes: PropTypes.func,
        addFish: PropTypes.func
    }
    state = {
        uid: null,
        owner: null
    }
    authHandler = async (authData) => {
        // 1. lookup the current store in firebase db
        const store = await base.fetch(this.props.storeId, {context: this})
        console.log(store);
        // 2. claim it if there is no owner
        if( !store.owner ){
            // save it as our own
            await base.post(`${this.props.storeId}/owner`,{
                data: authData.user.uid
            })
        }
        // 3. set state of the inventory component to reflect the current user
        this.setState({
            uid: authData.user.uid,
            owner: store.owner || authData.user.uid
        })
        console.log(authData);
    }

    authenticate = (provider) => {
        const authProvider = new firebase.auth[[`${provider}AuthProvider`]]();
        firebaseApp.auth().signInWithPopup(authProvider).then(this.authHandler);
    }

    logout = async () => {
        await firebase.auth().signOut();
        this.setState({ uid:null } );
    }

    render(){
        const logout = <button onClick={this.logout}>Logout</button>

        // check if they are logged in
        if(!this.state.uid){
            return (<Login authenticate={this.authenticate} /> );
        }

        // check if they are not the owner of the store
        if( this.state.uid !== this.state.owner ){
            <div><p>sorry you are not the owner!</p></div>
            {logout}
        }

        // they must be the owner, render the inventory
        return(
            <div className="inventory">
                <h2>Inventory</h2>
                {logout}
                {Object.keys(this.props.fishes).map( key =>
                    <EditFishForm
                        key={key}
                        index={key}
                        fish={this.props.fishes[key]}
                        updateFish={this.props.updateFish}
                        deleteFish={this.props.deleteFish}
                    />
                )}
                <AddFishForm addFish={this.props.addFish}></AddFishForm>
                <button onClick={this.props.loadSampleFishes}>Load Sample Fishes</button>
            </div>
        );

    }
}
export default Inventory;