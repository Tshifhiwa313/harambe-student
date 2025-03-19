const express = require('express');
const passport = require('passport');
const session = require('express-session');
const LocalStrategy = require('passport-local').Strategy;

const app = express();

// ...existing code...

// Configure session middleware
app.use(session({ secret: 'your_secret_key', resave: false, saveUninitialized: false }));

// Initialize Passport and restore authentication state, if any, from the session
app.use(passport.initialize());
app.use(passport.session());

// Define a local strategy for Passport
passport.use(new LocalStrategy(
  function(username, password, done) {
    // Replace this with your actual user authentication logic
    if (username === 'admin' && password === 'adminpassword') {
      return done(null, { username: 'admin' });
    } else {
      return done(null, false, { message: 'Incorrect credentials.' });
    }
  }
));

// Serialize user information into the session
passport.serializeUser(function(user, done) {
  done(null, user.username);
});

// Deserialize user information from the session
passport.deserializeUser(function(username, done) {
  // Replace this with your actual user retrieval logic
  if (username === 'admin') {
    done(null, { username: 'admin' });
  } else {
    done(new Error('User not found'));
  }
});

// ...existing code...

const authRoutes = require('./routes/auth');
const adminRoutes = require('./routes/admin');

app.use('/', authRoutes);
app.use('/', adminRoutes);

// Add event listener to the button
app.use((req, res, next) => {
  res.on('finish', () => {
    const script = `
      document.addEventListener('DOMContentLoaded', () => {
        const button = document.querySelector('a.btn.btn-sm.btn-outline-primary');
        if (button) {
          button.addEventListener('click', (event) => {
            console.log('clicked');
            const href = button.getAttribute('href');
            window.location.href = href;
          });
        }
      });
    `;
    res.send(`<script>${script}</script>`);
  });
  next();
});

// ...existing code...

app.listen(3000, () => {
  console.log('Server is running on port 3000');
});
