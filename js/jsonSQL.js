'use strict'

const jsonSQL =
{
    query: {
        get: function (query) { console.log(query) },
        set: function (query) { return query },
        update: function () { console.log("update") },
        replace: function (query = {}, searchvalue = [], newvalue = []) { 
            let str = JSON.stringify(query);
            searchvalue.forEach((element, index) => { str = str.replace(element, newvalue[index]) });
            return JSON.parse(str);
        }
    }
}

export default jsonSQL;