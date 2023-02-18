import React from "react";
import PropTypes from "prop-types";
import Header from "./Header";
import Order from "./Order";
import Inventory from "./Inventory";
import sampleFishes from "../sample-fishes";
import Fish from "./Fish";
import base from "../base";

class App extends React.Component {
    state = {
      fishes: {},
      order: {}
    };

    static propTypes = {
        match: PropTypes.object
    }

    componentDidMount() {
        const {params} = this.props.match;
        // first reinstate our localstorage
        const localStorageRef = localStorage.getItem(params.storeId);
        if (localStorageRef) {
            this.setState({order: JSON.parse(localStorageRef)});
        }
        this.ref = base.syncState(`${params.storeId}/fishes`, {
            context: this,
            state: 'fishes'
        });
    }

    componentDidUpdate(prevProps, prevState, snapshot) {
        localStorage.setItem(this.props.match.params.storeId,JSON.stringify(this.state.order));
    }

    componentWillUnmount() {
        base.removeBinding(this.ref);
    }

    addFish = (fish) => {
        // take copy of the existing state
        const fishes = { ...this.state.fishes };
        // add new fish to that fishes var
        fishes[`fish${Date.now()}`] = fish;
        // set the new fishes object to state
        this.setState({
            fishes: fishes
        });
        // console.log("adding a fish");
    };

    updateFish = (index, updatedFish) => {
        // 1. take copy of existing state
        const fishes = { ...this.state.fishes };
        // 2. update that state
        fishes[index]= updatedFish;
        // 3. ste to stat
        this.setState({
            fishes: fishes
        });
    }

    deleteFish = (key) => {
        // 1. take copy of state
        const fishes = { ...this.state.fishes };
        // 2. update the state
        fishes[key] = null;
        // 3. update state
        this.setState({
            fishes: fishes
        });

    }

    loadSampleFishes = () => {
        this.setState({
            fishes: sampleFishes
        })
    }

    addToOrder = (key) => {
        // 1. take copy of state
        const order = { ...this.state.order };
        // 2. either add to order or update the number
        order[key] = order[key] + 1 || 1;
        // 3. call set state to update state object.
        this.setState( {order});
    }

    removeFromOrder = (key) => {
        const order = {...this.state.order};
        delete order[key];
        this.setState({order: order})
    }

    render(){
        return (
            <div className="catch-of-the-day" >
                <div className="menu">
                    <Header tagline="Fresh Seafood Market" />
                    <ul className="fishes">
                        {Object.keys(this.state.fishes).map( key => (
                            <Fish
                                key={key}
                                index={key}
                                details={this.state.fishes[key]}
                                addToOrder={this.addToOrder}
                                 />
                            ))}
                    </ul>
                </div>
                <Order fishes={this.state.fishes} order={this.state.order} removeFromOrder={this.removeFromOrder} />
                <Inventory
                    addFish={this.addFish}
                    updateFish={this.updateFish}
                    deleteFish={this.deleteFish}
                    loadSampleFishes={this.loadSampleFishes}
                    fishes={this.state.fishes}
                    storeId={this.props.match.params.storeId}
                />
            </div>
        );
    }
}

export default App;
