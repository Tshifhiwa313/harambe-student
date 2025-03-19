const sqlite3 = require('sqlite3').verbose();
const db = new sqlite3.Database('./database.sqlite');
let credentialsCleared = false;

function clearCredentials() {
    if (!credentialsCleared) {
        db.serialize(() => {
            db.run("DELETE FROM users", (err) => {
                if (err) {
                    console.error('Error clearing credentials:', err.message);
                } else {
                    console.log('Credentials cleared.');
                    credentialsCleared = true;
                }
            });
        });
    }
}

module.exports = clearCredentials;
