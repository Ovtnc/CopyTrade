require('dotenv').config();
const mysql = require('mysql2/promise');
const { Web3 } = require('web3');
const TronWeb = require('tronweb');

// Database connection
const dbConfig = {
    host: process.env.DB_HOST || 'localhost',
    port: process.env.DB_PORT || 8889,
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASSWORD || 'root',
    database: process.env.DB_NAME || 'copystar_net',
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0
};

// Web3 instances
let web3Eth, web3Bsc, tronWeb;

// Token contract addresses
const TOKEN_CONTRACTS = {
    eth: {
        usdt: process.env.USDT_ETH_CONTRACT || '0xdAC17F958D2ee523a2206206994597C13D831ec7',
        usdc: process.env.USDC_ETH_CONTRACT || '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48'
    },
    bsc: {
        usdt: process.env.USDT_BSC_CONTRACT || '0x55d398326f99059fF775485246999027B3197955',
        usdc: process.env.USDC_BSC_CONTRACT || '0x8AC76a51cc950d9822D68b83fE1Ad97B32Cd580d'
    },
    trx: {
        usdt: process.env.USDT_TRON_CONTRACT || 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
        usdc: process.env.USDC_TRON_CONTRACT || 'TEkxiTehnzSmSe2XqrBj4w32RUN966rdz8n'
    }
};

// ERC20 Token ABI (for USDT, USDC transfers)
const ERC20_ABI = [
    {
        "constant": true,
        "inputs": [],
        "name": "decimals",
        "outputs": [{"name": "", "type": "uint8"}],
        "type": "function"
    },
    {
        "constant": true,
        "inputs": [{"name": "_owner", "type": "address"}],
        "name": "balanceOf",
        "outputs": [{"name": "balance", "type": "uint256"}],
        "type": "function"
    },
    {
        "anonymous": false,
        "inputs": [
            {"indexed": true, "name": "from", "type": "address"},
            {"indexed": true, "name": "to", "type": "address"},
            {"indexed": false, "name": "value", "type": "uint256"}
        ],
        "name": "Transfer",
        "type": "event"
    }
];

/**
 * Initialize blockchain connections
 */
async function initializeBlockchain() {
    try {
        // Initialize Ethereum
        if (process.env.ETH_RPC_URL) {
            web3Eth = new Web3(process.env.ETH_RPC_URL);
            console.log('✓ Ethereum connection initialized');
        }

        // Initialize BSC
        if (process.env.BSC_RPC_URL) {
            web3Bsc = new Web3(process.env.BSC_RPC_URL);
            console.log('✓ BSC connection initialized');
        }

        // Initialize Tron
        if (process.env.TRON_RPC_URL) {
            tronWeb = new TronWeb({
                fullHost: process.env.TRON_RPC_URL
            });
            console.log('✓ Tron connection initialized');
        }
    } catch (error) {
        console.error('Error initializing blockchain connections:', error);
    }
}

/**
 * Get database connection
 */
async function getDbConnection() {
    return await mysql.createConnection(dbConfig);
}

/**
 * Get all pending deposit transactions
 */
async function getPendingDeposits() {
    const conn = await getDbConnection();
    try {
        const [rows] = await conn.execute(
            `SELECT t.id, t.user_id, t.network, t.token, t.amount, t.address, t.tx_hash, u.eth_wallet_address, u.tron_wallet_address
             FROM transactions t
             INNER JOIN users u ON t.user_id = u.id
             WHERE t.type = 'deposit' AND t.status = 'pending'
             ORDER BY t.created_at ASC`
        );
        return rows;
    } finally {
        await conn.end();
    }
}

/**
 * Get all users with wallet addresses
 */
async function getAllUsersWithWallets() {
    const conn = await getDbConnection();
    try {
        const [rows] = await conn.execute(
            `SELECT id, eth_wallet_address, tron_wallet_address
             FROM users
             WHERE (eth_wallet_address IS NOT NULL AND eth_wallet_address != '')
                OR (tron_wallet_address IS NOT NULL AND tron_wallet_address != '')
             ORDER BY id ASC`
        );
        return rows;
    } finally {
        await conn.end();
    }
}

/**
 * Check if transaction already exists
 */
async function transactionExists(txHash) {
    const conn = await getDbConnection();
    try {
        const [rows] = await conn.execute(
            `SELECT id FROM transactions WHERE tx_hash = ?`,
            [txHash]
        );
        return rows.length > 0;
    } finally {
        await conn.end();
    }
}

/**
 * Create new deposit transaction
 */
async function createDepositTransaction(userId, network, token, amount, txHash, address) {
    const conn = await getDbConnection();
    try {
        await conn.execute(
            `INSERT INTO transactions (user_id, type, network, token, amount, address, tx_hash, status, created_at, updated_at)
             VALUES (?, 'deposit', ?, ?, ?, ?, ?, 'completed', NOW(), NOW())`,
            [userId, network, token, amount, address, txHash]
        );
        return true;
    } catch (error) {
        console.error('Error creating deposit transaction:', error);
        return false;
    } finally {
        await conn.end();
    }
}

/**
 * Get user wallet address for network
 */
function getUserWalletAddress(user, network) {
    if (network === 'eth' || network === 'bnb') {
        return user.eth_wallet_address;
    } else if (network === 'trx') {
        return user.tron_wallet_address;
    }
    return null;
}

/**
 * Check Ethereum/BSC transaction
 */
async function checkEthereumTransaction(transaction, web3Instance, network) {
    try {
        const walletAddress = getUserWalletAddress(transaction, network);
        if (!walletAddress) {
            console.log(`No wallet address for user ${transaction.user_id} on network ${network}`);
            return null;
        }

        const checksumAddress = web3Instance.utils.toChecksumAddress(walletAddress);
        
        // If tx_hash exists, check that specific transaction
        if (transaction.tx_hash) {
            const tx = await web3Instance.eth.getTransactionReceipt(transaction.tx_hash);
            if (tx && tx.status) {
                return await processEthereumTransaction(tx, transaction, web3Instance, network);
            }
        }

        // Check for token transfers (USDT, USDC)
        if (transaction.token === 'usdt' || transaction.token === 'usdc') {
            return await checkTokenTransfer(transaction, web3Instance, network, checksumAddress);
        }

        // Check for native token transfers (ETH, BNB)
        if (transaction.token === 'eth' || transaction.token === 'bnb') {
            return await checkNativeTransfer(transaction, web3Instance, network, checksumAddress);
        }

        return null;
    } catch (error) {
        console.error(`Error checking ${network} transaction:`, error.message);
        return null;
    }
}

/**
 * Check token transfer (USDT, USDC)
 */
async function checkTokenTransfer(transaction, web3Instance, network, walletAddress) {
    try {
        const tokenContract = TOKEN_CONTRACTS[network][transaction.token];
        if (!tokenContract) {
            console.log(`Token contract not found for ${network} ${transaction.token}`);
            return null;
        }

        const contract = new web3Instance.eth.Contract(ERC20_ABI, tokenContract);
        
        // Get current block
        const currentBlock = await web3Instance.eth.getBlockNumber();
        const fromBlock = Math.max(currentBlock - 1000, 0); // Check last 1000 blocks

        // Get Transfer events
        const events = await contract.getPastEvents('Transfer', {
            filter: { to: walletAddress },
            fromBlock: fromBlock,
            toBlock: 'latest'
        });

        // Find matching transfer
        for (const event of events) {
            const value = event.returnValues.value;
            const decimals = await contract.methods.decimals().call();
            const amount = parseFloat(value) / Math.pow(10, decimals);
            
            // Check if this matches the expected amount (with small tolerance)
            const expectedAmount = parseFloat(transaction.amount);
            const tolerance = 0.01; // 1% tolerance
            
            if (Math.abs(amount - expectedAmount) <= (expectedAmount * tolerance)) {
                return {
                    txHash: event.transactionHash,
                    amount: amount,
                    from: event.returnValues.from,
                    to: event.returnValues.to,
                    blockNumber: event.blockNumber
                };
            }
        }

        return null;
    } catch (error) {
        console.error(`Error checking token transfer:`, error.message);
        return null;
    }
}

/**
 * Check native token transfer (ETH, BNB)
 */
async function checkNativeTransfer(transaction, web3Instance, network, walletAddress) {
    try {
        const currentBlock = await web3Instance.eth.getBlockNumber();
        const fromBlock = Math.max(currentBlock - 1000, 0);

        // Get transactions in recent blocks
        for (let i = currentBlock; i >= fromBlock; i--) {
            const block = await web3Instance.eth.getBlock(i, true);
            if (!block || !block.transactions) continue;

            for (const tx of block.transactions) {
                if (tx.to && web3Instance.utils.toChecksumAddress(tx.to) === walletAddress) {
                    const value = web3Instance.utils.fromWei(tx.value, 'ether');
                    const expectedAmount = parseFloat(transaction.amount);
                    const tolerance = 0.01;

                    if (Math.abs(parseFloat(value) - expectedAmount) <= (expectedAmount * tolerance)) {
                        return {
                            txHash: tx.hash,
                            amount: parseFloat(value),
                            from: tx.from,
                            to: tx.to,
                            blockNumber: i
                        };
                    }
                }
            }
        }

        return null;
    } catch (error) {
        console.error(`Error checking native transfer:`, error.message);
        return null;
    }
}

/**
 * Check Tron transaction
 */
async function checkTronTransaction(transaction) {
    try {
        const walletAddress = getUserWalletAddress(transaction, 'trx');
        if (!walletAddress) {
            console.log(`No Tron wallet address for user ${transaction.user_id}`);
            return null;
        }

        // If tx_hash exists, check that specific transaction
        if (transaction.tx_hash) {
            const tx = await tronWeb.trx.getTransaction(transaction.tx_hash);
            if (tx && tx.ret && tx.ret[0].contractRet === 'SUCCESS') {
                return await processTronTransaction(tx, transaction);
            }
        }

        // Check for token transfers (USDT, USDC)
        if (transaction.token === 'usdt' || transaction.token === 'usdc') {
            return await checkTronTokenTransfer(transaction, walletAddress);
        }

        // Check for native TRX transfers
        if (transaction.token === 'trx') {
            return await checkTronNativeTransfer(transaction, walletAddress);
        }

        return null;
    } catch (error) {
        console.error('Error checking Tron transaction:', error.message);
        return null;
    }
}

/**
 * Check Tron token transfer (USDT, USDC)
 */
async function checkTronTokenTransfer(transaction, walletAddress) {
    try {
        const tokenContract = TOKEN_CONTRACTS.trx[transaction.token];
        if (!tokenContract) {
            console.log(`Token contract not found for TRX ${transaction.token}`);
            return null;
        }

        // Get account transactions
        const account = await tronWeb.trx.getAccount(walletAddress);
        if (!account) return null;

        // Get transactions from the account
        const transactions = await tronWeb.getEventResult(tokenContract, {
            eventName: 'Transfer',
            blockNumber: 'latest',
            size: 200
        });

        // Find matching transfer
        for (const event of transactions) {
            if (event.result && event.result.to && 
                tronWeb.address.fromHex(event.result.to) === walletAddress) {
                
                const decimals = transaction.token === 'usdt' ? 6 : 6; // USDT and USDC both use 6 decimals on Tron
                const value = parseFloat(event.result.value) / Math.pow(10, decimals);
                const expectedAmount = parseFloat(transaction.amount);
                const tolerance = 0.01;

                if (Math.abs(value - expectedAmount) <= (expectedAmount * tolerance)) {
                    return {
                        txHash: event.transaction,
                        amount: value,
                        from: event.result.from ? tronWeb.address.fromHex(event.result.from) : null,
                        to: tronWeb.address.fromHex(event.result.to),
                        blockNumber: event.block_number
                    };
                }
            }
        }

        return null;
    } catch (error) {
        console.error('Error checking Tron token transfer:', error.message);
        return null;
    }
}

/**
 * Check Tron native TRX transfer
 */
async function checkTronNativeTransfer(transaction, walletAddress) {
    try {
        // Get account transactions
        const transactions = await tronWeb.trx.getTransactionsFromAddress(walletAddress, 200);
        
        for (const tx of transactions) {
            if (tx.raw_data && tx.raw_data.contract) {
                for (const contract of tx.raw_data.contract) {
                    if (contract.type === 'TransferContract') {
                        const toAddress = tronWeb.address.fromHex(contract.parameter.value.to_address);
                        const amount = parseFloat(contract.parameter.value.amount) / 1000000; // TRX has 6 decimals

                        if (toAddress === walletAddress) {
                            const expectedAmount = parseFloat(transaction.amount);
                            const tolerance = 0.01;

                            if (Math.abs(amount - expectedAmount) <= (expectedAmount * tolerance)) {
                                return {
                                    txHash: tx.txID,
                                    amount: amount,
                                    from: contract.parameter.value.owner_address ? 
                                          tronWeb.address.fromHex(contract.parameter.value.owner_address) : null,
                                    to: toAddress,
                                    blockNumber: tx.blockNumber
                                };
                            }
                        }
                    }
                }
            }
        }

        return null;
    } catch (error) {
        console.error('Error checking Tron native transfer:', error.message);
        return null;
    }
}

/**
 * Process Ethereum transaction result
 */
async function processEthereumTransaction(tx, transaction, web3Instance, network) {
    // This would process a specific transaction receipt
    // Implementation depends on whether it's a token transfer or native transfer
    return {
        txHash: tx.transactionHash,
        blockNumber: tx.blockNumber,
        status: tx.status
    };
}

/**
 * Process Tron transaction result
 */
async function processTronTransaction(tx, transaction) {
    return {
        txHash: tx.txID,
        blockNumber: tx.blockNumber,
        status: tx.ret[0].contractRet
    };
}

/**
 * Update transaction status and user balance
 */
async function updateTransactionAndBalance(transactionId, txHash, amount, networkFee = 0) {
    const conn = await getDbConnection();
    try {
        await conn.beginTransaction();

        // Update transaction
        await conn.execute(
            `UPDATE transactions 
             SET status = 'completed', tx_hash = ?, receive_amount = ?, network_fee = ?, updated_at = NOW()
             WHERE id = ?`,
            [txHash, amount, networkFee, transactionId]
        );

        // Get transaction details
        const [txRows] = await conn.execute(
            `SELECT user_id, amount FROM transactions WHERE id = ?`,
            [transactionId]
        );

        if (txRows.length > 0) {
            const userId = txRows[0].user_id;
            const depositAmount = parseFloat(amount);

            // Update user balance
            await conn.execute(
                `UPDATE users 
                 SET balance = balance + ?, 
                     withdrawable_balance = withdrawable_balance + ?,
                     updated_at = NOW()
                 WHERE id = ?`,
                [depositAmount, depositAmount, userId]
            );

            console.log(`✓ Updated balance for user ${userId}: +$${depositAmount}`);
        }

        await conn.commit();
        return true;
    } catch (error) {
        await conn.rollback();
        console.error('Error updating transaction and balance:', error);
        return false;
    } finally {
        await conn.end();
    }
}

/**
 * Monitor all wallet addresses for new deposits
 */
async function monitorAllWallets() {
    console.log('\n[', new Date().toISOString(), '] Scanning all wallet addresses for new deposits...');

    try {
        const users = await getAllUsersWithWallets();
        
        if (users.length === 0) {
            console.log('No users with wallet addresses found.');
            return;
        }

        console.log(`Scanning ${users.length} user wallet(s)...`);

        for (const user of users) {
            // Check Ethereum/BSC wallets
            if (user.eth_wallet_address && (web3Eth || web3Bsc)) {
                await checkWalletForDeposits(user, user.eth_wallet_address, 'eth', web3Eth);
                await checkWalletForDeposits(user, user.eth_wallet_address, 'bnb', web3Bsc);
            }

            // Check Tron wallet
            if (user.tron_wallet_address && tronWeb) {
                await checkTronWalletForDeposits(user, user.tron_wallet_address);
            }
        }
    } catch (error) {
        console.error('Error in monitorAllWallets:', error);
    }
}

/**
 * Check wallet for new deposits (Ethereum/BSC)
 */
async function checkWalletForDeposits(user, walletAddress, network, web3Instance) {
    if (!web3Instance || !walletAddress) return;

    try {
        const checksumAddress = web3Instance.utils.toChecksumAddress(walletAddress);
        
        // Check for token transfers (USDT, USDC)
        for (const token of ['usdt', 'usdc']) {
            const tokenContract = TOKEN_CONTRACTS[network][token];
            if (!tokenContract) continue;

            const contract = new web3Instance.eth.Contract(ERC20_ABI, tokenContract);
            const currentBlock = await web3Instance.eth.getBlockNumber();
            const fromBlock = Math.max(currentBlock - 100, 0); // Check last 100 blocks

            const events = await contract.getPastEvents('Transfer', {
                filter: { to: checksumAddress },
                fromBlock: fromBlock,
                toBlock: 'latest'
            });

            for (const event of events) {
                // Check if transaction already processed
                const exists = await transactionExists(event.transactionHash);
                if (exists) continue;

                const value = event.returnValues.value;
                const decimals = await contract.methods.decimals().call();
                const amount = parseFloat(value) / Math.pow(10, decimals);

                if (amount > 0) {
                    console.log(`\n✓ New deposit found for user ${user.id}:`);
                    console.log(`  Network: ${network}, Token: ${token}, Amount: ${amount}`);
                    console.log(`  TX Hash: ${event.transactionHash}`);

                    // Create transaction and update balance
                    const created = await createDepositTransaction(
                        user.id,
                        network,
                        token,
                        amount,
                        event.transactionHash,
                        walletAddress
                    );

                    if (created) {
                        await updateBalanceOnly(user.id, amount);
                        console.log(`✓ Deposit processed for user ${user.id}`);
                    }
                }
            }
        }

        // Check for native token transfers (ETH, BNB)
        const currentBlock = await web3Instance.eth.getBlockNumber();
        const fromBlock = Math.max(currentBlock - 100, 0);
        const token = network === 'eth' ? 'eth' : 'bnb';

        for (let i = currentBlock; i >= fromBlock; i--) {
            const block = await web3Instance.eth.getBlock(i, true);
            if (!block || !block.transactions) continue;

            for (const tx of block.transactions) {
                if (tx.to && web3Instance.utils.toChecksumAddress(tx.to) === checksumAddress) {
                    const exists = await transactionExists(tx.hash);
                    if (exists) continue;

                    const value = web3Instance.utils.fromWei(tx.value, 'ether');
                    const amount = parseFloat(value);

                    if (amount > 0) {
                        console.log(`\n✓ New deposit found for user ${user.id}:`);
                        console.log(`  Network: ${network}, Token: ${token}, Amount: ${amount}`);
                        console.log(`  TX Hash: ${tx.hash}`);

                        const created = await createDepositTransaction(
                            user.id,
                            network,
                            token,
                            amount,
                            tx.hash,
                            walletAddress
                        );

                        if (created) {
                            await updateBalanceOnly(user.id, amount);
                            console.log(`✓ Deposit processed for user ${user.id}`);
                        }
                    }
                }
            }
        }
    } catch (error) {
        console.error(`Error checking ${network} wallet for user ${user.id}:`, error.message);
    }
}

/**
 * Check Tron wallet for new deposits
 */
async function checkTronWalletForDeposits(user, walletAddress) {
    if (!tronWeb || !walletAddress) return;

    try {
        // Check for token transfers (USDT, USDC)
        for (const token of ['usdt', 'usdc']) {
            const tokenContract = TOKEN_CONTRACTS.trx[token];
            if (!tokenContract) continue;

            const transactions = await tronWeb.getEventResult(tokenContract, {
                eventName: 'Transfer',
                blockNumber: 'latest',
                size: 100
            });

            for (const event of transactions) {
                if (event.result && event.result.to) {
                    const toAddress = tronWeb.address.fromHex(event.result.to);
                    if (toAddress !== walletAddress) continue;

                    const exists = await transactionExists(event.transaction);
                    if (exists) continue;

                    const decimals = 6; // USDT and USDC use 6 decimals on Tron
                    const value = parseFloat(event.result.value) / Math.pow(10, decimals);

                    if (value > 0) {
                        console.log(`\n✓ New deposit found for user ${user.id}:`);
                        console.log(`  Network: trx, Token: ${token}, Amount: ${value}`);
                        console.log(`  TX Hash: ${event.transaction}`);

                        const created = await createDepositTransaction(
                            user.id,
                            'trx',
                            token,
                            value,
                            event.transaction,
                            walletAddress
                        );

                        if (created) {
                            await updateBalanceOnly(user.id, value);
                            console.log(`✓ Deposit processed for user ${user.id}`);
                        }
                    }
                }
            }
        }

        // Check for native TRX transfers
        const transactions = await tronWeb.trx.getTransactionsFromAddress(walletAddress, 100);
        
        for (const tx of transactions) {
            if (tx.raw_data && tx.raw_data.contract) {
                for (const contract of tx.raw_data.contract) {
                    if (contract.type === 'TransferContract') {
                        const toAddress = tronWeb.address.fromHex(contract.parameter.value.to_address);
                        if (toAddress !== walletAddress) continue;

                        const exists = await transactionExists(tx.txID);
                        if (exists) continue;

                        const amount = parseFloat(contract.parameter.value.amount) / 1000000;

                        if (amount > 0) {
                            console.log(`\n✓ New deposit found for user ${user.id}:`);
                            console.log(`  Network: trx, Token: trx, Amount: ${amount}`);
                            console.log(`  TX Hash: ${tx.txID}`);

                            const created = await createDepositTransaction(
                                user.id,
                                'trx',
                                'trx',
                                amount,
                                tx.txID,
                                walletAddress
                            );

                            if (created) {
                                await updateBalanceOnly(user.id, amount);
                                console.log(`✓ Deposit processed for user ${user.id}`);
                            }
                        }
                    }
                }
            }
        }
    } catch (error) {
        console.error(`Error checking Tron wallet for user ${user.id}:`, error.message);
    }
}

/**
 * Update balance only (for new deposits)
 */
async function updateBalanceOnly(userId, amount) {
    const conn = await getDbConnection();
    try {
        await conn.execute(
            `UPDATE users 
             SET balance = balance + ?, 
                 withdrawable_balance = withdrawable_balance + ?,
                 updated_at = NOW()
             WHERE id = ?`,
            [amount, amount, userId]
        );
        return true;
    } catch (error) {
        console.error('Error updating balance:', error);
        return false;
    } finally {
        await conn.end();
    }
}

/**
 * Main monitoring function
 */
async function monitorDeposits() {
    console.log('\n[', new Date().toISOString(), '] Checking for deposits...');

    try {
        // First, check pending deposits
        const pendingDeposits = await getPendingDeposits();
        
        if (pendingDeposits.length > 0) {
            console.log(`Found ${pendingDeposits.length} pending deposit(s).`);

            for (const deposit of pendingDeposits) {
                console.log(`\nChecking deposit #${deposit.id} for user ${deposit.user_id}...`);
                console.log(`Network: ${deposit.network}, Token: ${deposit.token}, Amount: ${deposit.amount}`);

                let result = null;

                // Check based on network
                if (deposit.network === 'eth' && web3Eth) {
                    result = await checkEthereumTransaction(deposit, web3Eth, 'eth');
                } else if (deposit.network === 'bnb' && web3Bsc) {
                    result = await checkEthereumTransaction(deposit, web3Bsc, 'bnb');
                } else if (deposit.network === 'trx' && tronWeb) {
                    result = await checkTronTransaction(deposit);
                }

                if (result && result.txHash) {
                    console.log(`✓ Transaction found: ${result.txHash}`);
                    console.log(`  Amount: ${result.amount}`);
                    
                    const success = await updateTransactionAndBalance(
                        deposit.id,
                        result.txHash,
                        result.amount,
                        0 // Network fee can be calculated if needed
                    );

                    if (success) {
                        console.log(`✓ Deposit #${deposit.id} completed successfully!`);
                    } else {
                        console.log(`✗ Failed to update deposit #${deposit.id}`);
                    }
                } else {
                    console.log(`✗ No matching transaction found for deposit #${deposit.id}`);
                }
            }
        } else {
            console.log('No pending deposits found.');
        }

        // Then, scan all wallets for new deposits
        await monitorAllWallets();
    } catch (error) {
        console.error('Error in monitorDeposits:', error);
    }
}

/**
 * Start monitoring
 */
async function start() {
    console.log('Starting CopyStar Deposit Monitor...\n');
    
    // Initialize blockchain connections
    await initializeBlockchain();
    
    // Test database connection
    try {
        const conn = await getDbConnection();
        await conn.end();
        console.log('✓ Database connection successful\n');
    } catch (error) {
        console.error('✗ Database connection failed:', error);
        process.exit(1);
    }

    // Start monitoring loop
    const interval = parseInt(process.env.CHECK_INTERVAL) || 30000;
    console.log(`Monitoring deposits every ${interval / 1000} seconds...\n`);

    // Run immediately
    await monitorDeposits();

    // Then run on interval
    setInterval(async () => {
        await monitorDeposits();
    }, interval);
}

// Handle errors
process.on('unhandledRejection', (error) => {
    console.error('Unhandled rejection:', error);
});

process.on('SIGINT', () => {
    console.log('\nShutting down gracefully...');
    process.exit(0);
});

// Start the monitor
start().catch(console.error);

