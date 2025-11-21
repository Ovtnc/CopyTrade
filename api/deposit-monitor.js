// Vercel Serverless Function for Deposit Monitor
// This function will be called by Vercel Cron
// Note: Vercel automatically loads environment variables, no need for dotenv

import mysql from 'mysql2/promise';
import { Web3 } from 'web3';
import TronWeb from 'tronweb';

// Database connection
const dbConfig = {
    host: process.env.DB_HOST || 'localhost',
    port: process.env.DB_PORT || 3306,
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

// ERC20 Token ABI
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
        if (process.env.ETH_RPC_URL) {
            web3Eth = new Web3(process.env.ETH_RPC_URL);
        }
        if (process.env.BSC_RPC_URL) {
            web3Bsc = new Web3(process.env.BSC_RPC_URL);
        }
        if (process.env.TRON_RPC_URL) {
            tronWeb = new TronWeb({
                fullHost: process.env.TRON_RPC_URL
            });
        }
    } catch (error) {
        console.error('Error initializing blockchain:', error);
    }
}

/**
 * Get database connection
 */
async function getDbConnection() {
    return await mysql.createConnection(dbConfig);
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
 * Update balance only
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
                const exists = await transactionExists(event.transactionHash);
                if (exists) continue;

                const value = event.returnValues.value;
                const decimals = await contract.methods.decimals().call();
                const amount = parseFloat(value) / Math.pow(10, decimals);

                if (amount > 0) {
                    console.log(`New deposit: User ${user.id}, ${network} ${token}, Amount: ${amount}`);
                    
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
                        console.log(`New deposit: User ${user.id}, ${network} ${token}, Amount: ${amount}`);
                        
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

                    const decimals = 6;
                    const value = parseFloat(event.result.value) / Math.pow(10, decimals);

                    if (value > 0) {
                        console.log(`New deposit: User ${user.id}, trx ${token}, Amount: ${value}`);
                        
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
                            console.log(`New deposit: User ${user.id}, trx trx, Amount: ${amount}`);
                            
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
 * Monitor all wallets for new deposits
 */
async function monitorAllWallets() {
    console.log('Scanning all wallet addresses for new deposits...');

    try {
        const users = await getAllUsersWithWallets();
        
        if (users.length === 0) {
            console.log('No users with wallet addresses found.');
            return;
        }

        console.log(`Scanning ${users.length} user wallet(s)...`);

        for (const user of users) {
            if (user.eth_wallet_address && (web3Eth || web3Bsc)) {
                if (web3Eth) await checkWalletForDeposits(user, user.eth_wallet_address, 'eth', web3Eth);
                if (web3Bsc) await checkWalletForDeposits(user, user.eth_wallet_address, 'bnb', web3Bsc);
            }

            if (user.tron_wallet_address && tronWeb) {
                await checkTronWalletForDeposits(user, user.tron_wallet_address);
            }
        }
    } catch (error) {
        console.error('Error in monitorAllWallets:', error);
    }
}

/**
 * Main monitoring function
 */
async function monitorDeposits() {
    console.log(`[${new Date().toISOString()}] Starting deposit monitor...`);

    try {
        await initializeBlockchain();
        await monitorAllWallets();
        console.log(`[${new Date().toISOString()}] Deposit monitor completed.`);
        return { success: true, message: 'Deposit monitor completed successfully' };
    } catch (error) {
        console.error('Error in monitorDeposits:', error);
        return { success: false, error: error.message };
    }
}

/**
 * Vercel Serverless Function Handler
 */
export default async function handler(req, res) {
    // Only allow POST requests (for cron) or GET with secret key
    const secret = req.headers['x-vercel-cron'] || req.query.secret;
    const expectedSecret = process.env.CRON_SECRET;

    if (expectedSecret && secret !== expectedSecret) {
        return res.status(401).json({ error: 'Unauthorized' });
    }

    try {
        const result = await monitorDeposits();
        
        if (result.success) {
            return res.status(200).json({
                success: true,
                message: result.message,
                timestamp: new Date().toISOString()
            });
        } else {
            return res.status(500).json({
                success: false,
                error: result.error,
                timestamp: new Date().toISOString()
            });
        }
    } catch (error) {
        console.error('Handler error:', error);
        return res.status(500).json({
            success: false,
            error: error.message,
            timestamp: new Date().toISOString()
        });
    }
}

