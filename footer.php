<!-- Footer -->
<footer class="site-footer">
    <div class="footer-content">
        <div class="footer-links">
            <a href="#" class="footer-link">Whatsapp Channel</a>
            <a href="#" class="footer-link">Privacy & Terms</a>
        </div>
        <div class="copyright">© 2025 Qtro ISP Billing. All Rights Reserved.</div>
    </div>
</footer>

<style>
    .site-footer {
        background-color: var(--bg-secondary);
        padding: 1.5rem;
        margin-top: 2rem;
        border-top: 1px solid var(--bg-accent);
    }
    
    .footer-content {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 1rem;
        max-width: 1200px;
        margin: 0 auto;
    }
    
    .footer-links {
        display: flex;
        gap: 1.5rem;
    }
    
    .footer-link {
        color: var(--text-secondary);
        text-decoration: none;
        font-size: 0.9rem;
        transition: color 0.2s ease;
    }
    
    .footer-link:hover {
        color: var(--accent-blue);
    }
    
    .copyright {
        font-size: 0.85rem;
        color: var(--text-secondary);
    }
    
    @media (min-width: 768px) {
        .footer-content {
            flex-direction: row;
            justify-content: space-between;
        }
    }
</style> 