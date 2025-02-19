import React from "react"
import App from "./App"
import "./index.css"

document.addEventListener('DOMContentLoaded', function() {
  const container = document.getElementById('WM-admin-app');
  if (container) {
    const root = ReactDOM.createRoot(container);
    root.render(
      <React.StrictMode>
        <App />
      </React.StrictMode>
    );
  } else {
    console.error('Container element not found');
  }
});


