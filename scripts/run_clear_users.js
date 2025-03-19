const mysql = require('mysql');
const fs = require('fs');
const path = require('path');

const connection = mysql.createConnection({
  host: 'localhost',
  user: 'root',
  password: 'password',
  database: 'your_database_name'
});

const sqlFilePath = path.join(__dirname, 'clear_users.sql');
const sql = fs.readFileSync(sqlFilePath, 'utf8');

connection.connect();

connection.query(sql, (error, results) => {
  if (error) throw error;
  console.log('Database cleared and admin user created.');
});

connection.end();
