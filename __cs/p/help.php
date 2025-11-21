
    <title>Help - CopyStar</title>

</head>
<body>
<?php
// Require authentication
if (!isset($currentUser) || !$currentUser) {
    // Clear any invalid cookies
    if (isset($_COOKIE['auth_key'])) {
        setcookie('auth_key', '', time() - 3600, '/');
    }
    header("Location: " . WEB_URL . "/login");
    exit;
}

// Handle ticket creation
$ticketSuccess = false;
$ticketError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_ticket'])) {
    $subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
    $category = isset($_POST['category']) ? trim($_POST['category']) : '';
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    $priority = isset($_POST['priority']) ? trim($_POST['priority']) : 'medium';
    
    // Validation
    if (empty($subject)) {
        $ticketError = 'ticketSubjectRequired';
    } elseif (empty($category) || !in_array($category, ['technical', 'account', 'trading', 'payment', 'other'])) {
        $ticketError = 'ticketCategoryRequired';
    } elseif (empty($message)) {
        $ticketError = 'ticketMessageRequired';
    } elseif (!in_array($priority, ['low', 'medium', 'high', 'urgent'])) {
        $priority = 'medium';
    } else {
        // Generate ticket number
        $ticketNumber = 'TKT-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Check if ticket number already exists (very unlikely but check anyway)
        $checkStmt = $conn->prepare("SELECT id FROM support_tickets WHERE ticket_number = ?");
        $checkStmt->bind_param("s", $ticketNumber);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $checkStmt->close();
        
        if ($checkResult->num_rows > 0) {
            // Regenerate if exists
            $ticketNumber = 'TKT-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        }
        
        // Insert ticket
        $stmt = $conn->prepare("INSERT INTO support_tickets (user_id, ticket_number, subject, category, message, priority, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->bind_param("isssss", $currentUser['id'], $ticketNumber, $subject, $category, $message, $priority);
        
        if ($stmt->execute()) {
            $ticketSuccess = true;
        } else {
            $ticketError = 'ticketCreationFailed';
        }
        $stmt->close();
    }
}

// Get latest 3 tickets for current user
$tickets = [];
$stmt = $conn->prepare("SELECT id, ticket_number, subject, category, message, priority, status, admin_response, responded_at, created_at FROM support_tickets WHERE user_id = ? ORDER BY created_at DESC LIMIT 3");
$stmt->bind_param("i", $currentUser['id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $tickets[] = $row;
}
$stmt->close();

// Category names mapping
$categoryNames = [
    'technical' => 'technicalIssue',
    'account' => 'accountIssue',
    'trading' => 'tradingIssue',
    'payment' => 'paymentIssue',
    'other' => 'other'
];

// Status badge mapping
$statusBadges = [
    'pending' => ['class' => 'bg-warning text-dark', 'key' => 'pending'],
    'in_progress' => ['class' => 'bg-info', 'key' => 'inProgress'],
    'resolved' => ['class' => 'bg-success', 'key' => 'resolved'],
    'closed' => ['class' => 'bg-secondary', 'key' => 'closed']
];
?>
    <!-- Animated Background -->
    <div class="animated-bg"></div>
    
    <!-- Navigation -->
    <?php include(V_PATH."topnav.php"); ?>

    <!-- Help Section -->
    <section class="dashboard-section" style="padding-top: 120px;">
        <div class="container">
            <div class="row mb-4">
                <div class="col-12">
                    <h1 class="h2 fw-bold mb-0" data-key="help">Yardım</h1>
                    <p class="text-muted mb-0" data-key="helpSubtitle">Sık sorulan sorular ve destek talebi oluşturma</p>
                </div>
            </div>

            <?php if ($ticketSuccess): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <span data-key="ticketCreatedSuccess">Destek talebiniz başarıyla oluşturuldu! En kısa sürede size dönüş yapacağız.</span>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($ticketError): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <span data-key="<?php echo htmlspecialchars($ticketError); ?>">Hata oluştu!</span>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- FAQ Section -->
                <div class="col-12 col-lg-8">
                    <div class="glass-card">
                        <div class="d-flex align-items-center justify-content-between mb-4">
                            <h2 class="h4 fw-bold mb-0" data-key="faqTitle">Sık Sorulan Sorular</h2>
                            <i class="fas fa-question-circle text-primary"></i>
                        </div>
                        
                        <div class="accordion" id="helpAccordion">
                            <!-- FAQ 1: How to start copy trading? -->
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                        <span data-key="helpFaq1Question">How to start copy trading?</span>
                                    </button>
                                </h2>
                                <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                                    <div class="accordion-body">
                                        <span data-key="helpFaq1Answer">To start copy trading, first create an account and complete the verification process. Then, deposit funds into your account. Browse the list of available traders on the Traders page, review their performance metrics (ROI, followers, AUM), and click "Follow" on the trader you want to copy. Your account will automatically replicate their trades proportionally based on your allocated capital.</span>
                                    </div>
                                </div>
                            </div>

                            <!-- FAQ 2: How to become a leader trader? -->
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                        <span data-key="helpFaq2Question">How to become a leader trader?</span>
                                    </button>
                                </h2>
                                <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                                    <div class="accordion-body">
                                        <span data-key="helpFaq2Answer">To become a leader trader, you need to demonstrate consistent trading performance and meet our platform's requirements. Click the "Apply to be a Leader Trader" button on the Traders page and fill out the application form. Our team will review your trading history, risk management skills, and overall performance. If approved, you'll be able to share your trading strategies and earn commissions from followers who copy your trades.</span>
                                    </div>
                                </div>
                            </div>

                            <!-- FAQ 3: What is ROI (Return on Investment)? -->
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                        <span data-key="helpFaq3Question">What is ROI (Return on Investment)?</span>
                                    </button>
                                </h2>
                                <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                                    <div class="accordion-body">
                                        <span data-key="helpFaq3Answer">ROI (Return on Investment) is a percentage that shows the total return on an investment over a specific period. It's calculated by dividing the net profit by the initial investment and multiplying by 100. For example, if you invest $1,000 and earn $150, your ROI is 15%. A higher ROI indicates better performance, but it's important to consider risk factors and drawdowns when evaluating traders.</span>
                                    </div>
                                </div>
                            </div>

                            <!-- FAQ 4: What does Followers mean? -->
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                        <span data-key="helpFaq4Question">What does Followers mean?</span>
                                    </button>
                                </h2>
                                <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                                    <div class="accordion-body">
                                        <span data-key="helpFaq4Answer">Followers represent the number of users who are currently copying a trader's strategies. When you follow a trader, your account automatically replicates their trades. The follower count is an indicator of a trader's popularity and trust within the community. However, it's important to evaluate traders based on their performance metrics (ROI, AUM, MDD) rather than just follower count.</span>
                                    </div>
                                </div>
                            </div>

                            <!-- FAQ 5: What is AUM (Assets Under Management)? -->
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">
                                        <span data-key="helpFaq5Question">What is AUM (Assets Under Management)?</span>
                                    </button>
                                </h2>
                                <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                                    <div class="accordion-body">
                                        <span data-key="helpFaq5Answer">AUM (Assets Under Management) refers to the total amount of capital that followers have allocated to copy a specific trader. It represents the cumulative investment from all followers. A higher AUM typically indicates that more investors trust the trader with their capital. This metric helps you understand the scale of a trader's operations and can be a factor in your decision-making process.</span>
                                    </div>
                                </div>
                            </div>

                            <!-- FAQ 6: What is MDD (Maximum Drawdown)? -->
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq6">
                                        <span data-key="faq6Question">What is MDD (Maximum Drawdown)?</span>
                                    </button>
                                </h2>
                                <div id="faq6" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                                    <div class="accordion-body">
                                        <span data-key="faq6Answer">MDD (Maximum Drawdown) is the largest peak-to-trough decline in a trader's account value over a specific period. It measures the worst-case loss from a peak value. For example, if a trader's account reaches $10,000 and then drops to $7,000 before recovering, the MDD is 30%. A lower MDD indicates better risk management. It's crucial to consider MDD alongside ROI when evaluating traders, as it shows how much risk was taken to achieve returns.</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Active Tickets Section -->
                <div class="col-12 col-lg-4">
                    <div class="glass-card">
                        <div class="d-flex align-items-center justify-content-between mb-4">
                            <h2 class="h4 fw-bold mb-0" data-key="activeTickets">Aktif Destek Taleplerim</h2>
                            <i class="fas fa-ticket-alt text-primary"></i>
                        </div>
                        
                        <button class="btn btn-modern w-100 mb-4" data-bs-toggle="modal" data-bs-target="#createTicketModal" data-key="createTicket">
                            <i class="fas fa-plus me-2"></i><span data-key="createTicket">Yeni Destek Talebi Oluştur</span>
                        </button>

                        <!-- Active Tickets List -->
                        <div class="tickets-list">
                            <?php if (empty($tickets)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <p class="text-muted mb-0" data-key="noTicketsYet">Henüz destek talebiniz yok</p>
                            </div>
                            <?php else: ?>
                            <?php foreach ($tickets as $ticket): 
                                $date = new DateTime($ticket['created_at']);
                                $formattedDate = $date->format('d.m.Y');
                                $statusInfo = $statusBadges[$ticket['status']] ?? $statusBadges['pending'];
                                $categoryKey = $categoryNames[$ticket['category']] ?? 'other';
                                // Truncate message for display
                                $messagePreview = mb_substr($ticket['message'], 0, 60);
                                if (mb_strlen($ticket['message']) > 60) {
                                    $messagePreview .= '...';
                                }
                            ?>
                            <div class="ticket-item mb-3 p-3 rounded-3" style="background: var(--bg-secondary); border: 1px solid var(--border-color);">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div class="flex-grow-1">
                                        <h5 class="h6 fw-bold mb-1"><?php echo htmlspecialchars($ticket['subject']); ?></h5>
                                        <p class="text-muted small mb-0">#<?php echo htmlspecialchars($ticket['ticket_number']); ?></p>
                                    </div>
                                    <span class="badge <?php echo htmlspecialchars($statusInfo['class']); ?>" data-key="<?php echo htmlspecialchars($statusInfo['key']); ?>">
                                        <?php 
                                            echo $statusInfo['key'] == 'pending' ? 'Beklemede' : 
                                                ($statusInfo['key'] == 'inProgress' ? 'İşlemde' : 
                                                ($statusInfo['key'] == 'resolved' ? 'Çözüldü' : 'Kapatıldı')); 
                                        ?>
                                    </span>
                                </div>
                                <p class="text-muted small mb-2"><?php echo htmlspecialchars($messagePreview); ?></p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted" data-key="createdDate"><?php echo htmlspecialchars($formattedDate); ?></small>
                                    <button class="btn btn-sm btn-outline-modern" onclick="viewTicketDetails(<?php echo $ticket['id']; ?>)" data-key="viewDetails">
                                        <i class="fas fa-eye me-1"></i><span data-key="viewDetails">Detaylar</span>
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Contact Info Card -->
                    <div class="glass-card mt-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h3 class="h5 fw-bold mb-0" data-key="contact">İletişim Bilgileri</h3>
                            <i class="fas fa-headset text-info"></i>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="fas fa-envelope text-primary"></i>
                                <span class="text-muted small">support@copystar.net</span>
                            </div>
                            <div class="d-flex align-items-start gap-2 mb-2">
                                <i class="fas fa-map-marker-alt text-danger mt-1"></i>
                                <span class="text-muted small">138 Robinson Road, #15-01 Singapore 068906</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <i class="fas fa-clock text-warning"></i>
                                <span class="text-muted small" data-key="supportHours">7/24 Destek</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Create Ticket Modal -->
    <div class="modal fade" id="createTicketModal" tabindex="-1" aria-labelledby="createTicketModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content glass-card" style="border: 1px solid var(--border-color);">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                    <h5 class="modal-title fw-bold" id="createTicketModalLabel" data-key="createTicket">Destek Talebi Oluştur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="ticketForm" method="POST" action="">
                        <input type="hidden" name="create_ticket" value="1">
                        <div class="mb-3">
                            <label for="ticketSubject" class="form-label small text-muted mb-2" data-key="ticketSubject">Konu</label>
                            <input type="text" class="form-control form-control-modern" id="ticketSubject" name="subject" placeholder="Destek talebi konusu" data-key="ticketSubjectPlaceholder" required>
                        </div>

                        <div class="mb-3">
                            <label for="ticketCategory" class="form-label small text-muted mb-2" data-key="ticketCategory">Kategori</label>
                            <select class="form-select form-control-modern" id="ticketCategory" name="category" required>
                                <option value="" data-key="selectCategory">Kategori Seçin</option>
                                <option value="technical" data-key="technicalIssue">Teknik Sorun</option>
                                <option value="account" data-key="accountIssue">Hesap Sorunu</option>
                                <option value="trading" data-key="tradingIssue">İşlem Sorunu</option>
                                <option value="payment" data-key="paymentIssue">Ödeme Sorunu</option>
                                <option value="other" data-key="other">Diğer</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="ticketMessage" class="form-label small text-muted mb-2" data-key="ticketMessage">Mesaj</label>
                            <textarea class="form-control form-control-modern" id="ticketMessage" name="message" rows="6" placeholder="Sorununuzu detaylı olarak açıklayın" data-key="ticketMessagePlaceholder" required></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="ticketPriority" class="form-label small text-muted mb-2" data-key="ticketPriority">Öncelik</label>
                            <select class="form-select form-control-modern" id="ticketPriority" name="priority" required>
                                <option value="low" data-key="lowPriority">Düşük</option>
                                <option value="medium" selected data-key="mediumPriority">Orta</option>
                                <option value="high" data-key="highPriority">Yüksek</option>
                                <option value="urgent" data-key="urgentPriority">Acil</option>
                            </select>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-modern" data-bs-dismiss="modal" data-key="cancel">
                                <i class="fas fa-times me-2"></i><span data-key="cancel">İptal</span>
                            </button>
                            <button type="submit" class="btn btn-modern flex-grow-1" data-key="submitTicket">
                                <i class="fas fa-paper-plane me-2"></i><span data-key="submitTicket">Talep Gönder</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- View Ticket Details Modal -->
    <div class="modal fade" id="viewTicketModal" tabindex="-1" aria-labelledby="viewTicketModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content glass-card" style="border: 1px solid var(--border-color);">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                    <h5 class="modal-title fw-bold" id="viewTicketModalLabel" data-key="ticketDetails">Destek Talebi Detayları</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="ticketDetailsContent">
                    <!-- Content will be loaded dynamically -->
                </div>
            </div>
        </div>
    </div>

    <script>
        // Store tickets data for JavaScript
        const ticketsData = <?php echo json_encode($tickets); ?>;
        const categoryNames = <?php echo json_encode($categoryNames); ?>;
        const statusBadges = <?php echo json_encode($statusBadges); ?>;

        // View Ticket Details Function
        function viewTicketDetails(ticketId) {
            const ticket = ticketsData.find(t => t.id == ticketId);
            if (!ticket) {
                alert('Destek talebi bulunamadı!');
                return;
            }

            const date = new Date(ticket.created_at);
            const formattedDate = date.toLocaleDateString('tr-TR', { day: '2-digit', month: '2-digit', year: 'numeric' });
            const formattedTime = date.toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
            
            let respondedDate = '';
            if (ticket.responded_at) {
                const respDate = new Date(ticket.responded_at);
                respondedDate = respDate.toLocaleDateString('tr-TR', { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' ' + respDate.toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
            }

            const statusInfo = statusBadges[ticket.status] || statusBadges.pending;
            const categoryKey = categoryNames[ticket.category] || 'other';

            let html = `
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h6 class="fw-bold mb-1">${escapeHtml(ticket.subject)}</h6>
                            <p class="text-muted small mb-0">#${escapeHtml(ticket.ticket_number)}</p>
                        </div>
                        <span class="badge ${statusInfo.class}" data-key="${statusInfo.key}">
                            ${statusInfo.key === 'pending' ? 'Beklemede' : 
                              statusInfo.key === 'inProgress' ? 'İşlemde' : 
                              statusInfo.key === 'resolved' ? 'Çözüldü' : 'Kapatıldı'}
                        </span>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small text-muted mb-1" data-key="ticketCategory">Kategori</label>
                        <p class="mb-0"><span data-key="${categoryKey}">${getCategoryName(categoryKey)}</span></p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small text-muted mb-1" data-key="createdDate">Oluşturulma Tarihi</label>
                        <p class="mb-0">${formattedDate} ${formattedTime}</p>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label small text-muted mb-2" data-key="ticketMessage">Mesajınız</label>
                    <div class="p-3 rounded-3" style="background: var(--bg-secondary); border: 1px solid var(--border-color);">
                        <p class="mb-0" style="white-space: pre-wrap;">${escapeHtml(ticket.message)}</p>
                    </div>
                </div>
            `;

            if (ticket.admin_response) {
                html += `
                    <div class="mb-3">
                        <label class="form-label small text-muted mb-2" data-key="adminResponse">Yanıt</label>
                        <div class="p-3 rounded-3" style="background: var(--bg-primary); border: 1px solid var(--border-color);">
                            <p class="mb-0" style="white-space: pre-wrap;">${escapeHtml(ticket.admin_response)}</p>
                        </div>
                        ${respondedDate ? `<small class="text-muted" data-key="respondedAt">Yanıt Tarihi: ${respondedDate}</small>` : ''}
                    </div>
                `;
            } else {
                html += `
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        <span data-key="noResponseYet">Henüz yanıt verilmedi. En kısa sürede size dönüş yapılacaktır.</span>
                    </div>
                `;
            }

            document.getElementById('ticketDetailsContent').innerHTML = html;
            
            // Update translations
            if (typeof updateTranslations === 'function') {
                updateTranslations();
            }
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('viewTicketModal'));
            modal.show();
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function getCategoryName(key) {
            const names = {
                'technicalIssue': 'Teknik Sorun',
                'accountIssue': 'Hesap Sorunu',
                'tradingIssue': 'İşlem Sorunu',
                'paymentIssue': 'Ödeme Sorunu',
                'other': 'Diğer'
            };
            return names[key] || 'Diğer';
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-close create modal on success
            <?php if ($ticketSuccess): ?>
            const createModal = bootstrap.Modal.getInstance(document.getElementById('createTicketModal'));
            if (createModal) {
                createModal.hide();
            }
            <?php endif; ?>
        });
    </script>

