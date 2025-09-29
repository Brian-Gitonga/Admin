  // Tab switching functionality
  const tabs = document.querySelectorAll('.tab');
  const tabContents = document.querySelectorAll('.tab-content');
  
  tabs.forEach(tab => {
      tab.addEventListener('click', () => {
          const tabId = tab.getAttribute('data-tab');
          
          // Remove active class from all tabs and add to clicked tab
          tabs.forEach(t => t.classList.remove('active'));
          tab.classList.add('active');
          
          // Hide all tab contents and show the selected one
          tabContents.forEach(content => {
              content.classList.remove('active');
          });
          
          document.getElementById(`${tabId}-tab`).classList.add('active');
      });
  });
 
 // Payment gateway selection
 const paymentGateway = document.getElementById('payment-gateway');
 const mpesaSettings = document.getElementById('mpesa-settings');
 const bankSettings = document.getElementById('bank-settings');
 const tillSettings = document.getElementById('till-settings');
 
 paymentGateway.addEventListener('change', () => {
     const selectedGateway = paymentGateway.value;
     
     // Hide all settings
     mpesaSettings.style.display = 'none';
     bankSettings.style.display = 'none';
     tillSettings.style.display = 'none';
     
     // Show selected gateway settings
     if (selectedGateway === 'mpesa') {
         mpesaSettings.style.display = 'block';
     } else if (selectedGateway === 'bank') {
         bankSettings.style.display = 'block';
     } else if (selectedGateway === 'till') {
         tillSettings.style.display = 'block';
     }
 });
 
 // SMS provider selection
 const smsProvider = document.getElementById('sms-provider');
 const africasTalkingSettings = document.getElementById('africas-talking-settings');
 const twilioSettings = document.getElementById('twilio-settings');
 
 smsProvider.addEventListener('change', () => {
     const selectedProvider = smsProvider.value;
     
     // Hide all settings
     africasTalkingSettings.style.display = 'none';
     twilioSettings.style.display = 'none';
     
     // Show selected provider settings
     if (selectedProvider === 'africas-talking') {
         africasTalkingSettings.style.display = 'block';
     } else if (selectedProvider === 'twilio') {
         twilioSettings.style.display = 'block';
     }
     // Add other providers as needed
 });
 
 // File upload display
 const portalLogo = document.getElementById('portal-logo');
 const logoFileName = document.getElementById('logo-file-name');
 
 portalLogo.addEventListener('change', function() {
     if (this.files.length > 0) {
         logoFileName.textContent = this.files[0].name;
     } else {
         logoFileName.textContent = 'No file chosen';
     }
 });
 
 // Save settings
 const saveSettingsBtn = document.getElementById('save-settings-btn');
 
 saveSettingsBtn.addEventListener('click', () => {
     // Here you would typically collect all form data and send to backend
     
     // Show success message
     alert('Settings saved successfully!');
 });
 
 // Initialize on page load
 document.addEventListener('DOMContentLoaded', function() {
     // Initialize responsive behavior
     handleResize();
     
     // Initialize active nav items
     setActiveNavItem();
 });
 
 // Initialize responsive behavior
 handleResize();
 window.addEventListener('resize', handleResize);