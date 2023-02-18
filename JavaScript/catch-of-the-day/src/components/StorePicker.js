import React from "react";
import PropTypes from "prop-types";
import { getFunName } from "../helpers";


class StorePicker extends React.Component {
    static proptypes = {
        history: PropTypes.func
    }
    // the constructor way:
    /*constructor() {
        super();
        this.goToStore = this.goToStore.bind(this);
    }*/

    myInput = React.createRef();

    goToStore = (event) => {
        // stop form from submitting
        event.preventDefault();

        // get the text for the import
        //console.log(this.myInput.current.value)
        const storeName = this.myInput.current.value;

        // change the page to /store/submitted-name/
        this.props.history.push(`/store/${storeName}`);
    }

    render() {
        return (
            <form className="store-selector" onSubmit={this.goToStore}>
                <h2>Please enter a Store</h2>
                <input
                    type="text"
                    ref={this.myInput}
                    required
                    placeholder="Enter Store Name"
                    defaultValue={getFunName()}
                />
                <button type="submit">Vist Store -></button>
            </form>
        );
    }
}

export default StorePicker;