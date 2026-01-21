import { reactive } from 'vue';

const isProd = import.meta.env.PROD;
const API_BASE = isProd ? '/chat.php' : 'https://ownedge.com/chat.php';

export const chatStore = reactive({
    nickname: '',
    isConnected: false,
    showPopup: true,
    messages: [],
    users: [],
    pollingInterval: null,
    heartbeatInterval: null,
    isServerOnline: true,
    topic: { text: '...', author: '...', modified: '' },
    lastId: 0,
    
    async init() {
        // Mark joining time to avoid seeing historical messages
        this.lastId = (Date.now() / 1000) - 0.5; // Offset slightly to guarantee catching your own join message
        this.messages = [];
        
        await Promise.all([
            this.fetchTopic(),
            this.fetchMessages(),
            this.fetchUsers()
        ]);
        this.startPolling();
    },

    async fetchTopic() {
        try {
            const response = await fetch(`${API_BASE}?action=topic`);
            if (response.ok) {
                const data = await response.json();
                this.topic = { 
                    text: data.topic, 
                    author: data.author || 'Admin', 
                    modified: data.modified 
                };
                // Print topic to log
                this.messages.push({
                    id: 'topic-' + Date.now(),
                    type: 'system',
                    text: `*** Topic for #OWNEDGE: ${data.topic}`,
                    timestamp: new Date().toISOString()
                });
            }
        } catch (e) {}
    },

    async updateTopic(newText) {
        try {
            const response = await fetch(`${API_BASE}?action=topic`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                // Include nickname so we know who changed it
                body: JSON.stringify({ topic: newText, user: this.nickname })
            });
            if (response.ok) {
                const data = await response.json();
                this.topic = { 
                    text: data.topic, 
                    author: data.author || this.nickname, 
                    modified: data.modified 
                };
            }
        } catch (e) {}
    },

    async fetchMessages() {
        if (!this.lastId) return;
        try {
            const response = await fetch(`${API_BASE}?action=messages&since=${this.lastId}`);
            if (response.ok) {
                const data = await response.json();
                if (Array.isArray(data) && data.length > 0) {
                    // Filter out duplicates (though 'since' should handle it)
                    const newMsgs = data.filter(m => !this.messages.find(existing => existing.id === m.id));
                    if (newMsgs.length > 0) {
                        this.messages.push(...newMsgs);
                        this.lastId = data[data.length - 1].id;

                        // Sync header topic if anyone changed it
                        if (newMsgs.some(m => m.type === 'system' && m.text.includes('changed the topic to:'))) {
                            this.fetchTopic();
                        }
                    }
                }
                this.isServerOnline = true;
            } else {
                this.isServerOnline = false;
            }
        } catch (e) {
            this.isServerOnline = false;
        }
    },

    async fetchUsers() {
        try {
            const response = await fetch(`${API_BASE}?action=users`);
            if (response.ok) {
                const data = await response.json();
                if (Array.isArray(data)) {
                    this.users = data.filter(u => u !== this.nickname);
                } else {
                    console.error("fetchUsers: Expected array, got:", data);
                }
            } else {
                const body = await response.text();
                console.error(`fetchUsers Failed (${response.status}):`, body);
            }
        } catch (e) {
            console.error("fetchUsers Error:", e);
        }
    },

    async sendHeartbeat() {
        if (!this.isConnected || !this.nickname) return;
        try {
            await fetch(`${API_BASE}?action=presence`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ nickname: this.nickname })
            });
        } catch (e) {}
    },

    async addMessage(msg) {
        const localId = 'temp-' + Date.now() + Math.random();
        const localMsg = { 
            ...msg, 
            id: localId, 
            user: msg.user || this.nickname,
            timestamp: new Date().toISOString()
        };
        
        this.messages.push(localMsg);
        if (msg.localOnly) return;

        try {
            const response = await fetch(`${API_BASE}?action=messages`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(localMsg)
            });
            
            if (response.ok) {
                const verifiedMsg = await response.json();
                
                // 1. Check if background polling already grabbed this message
                const alreadySynced = this.messages.find(m => m.id === verifiedMsg.id);
                const tempIdx = this.messages.findIndex(m => m.id === localId);
                
                if (alreadySynced) {
                    // It's already there with the real ID, so just discard the temp one
                    if (tempIdx !== -1) this.messages.splice(tempIdx, 1);
                } else {
                    // Not there yet, so "upgrade" the temp one to the real ID
                    if (tempIdx !== -1) {
                        this.messages[tempIdx].id = verifiedMsg.id;
                        this.messages[tempIdx].timestamp = verifiedMsg.timestamp;
                    }
                }
                
                // 2. Advance lastId to ensure we don't fetch it again
                if (parseFloat(verifiedMsg.id) > parseFloat(this.lastId)) {
                    this.lastId = verifiedMsg.id;
                }
                
                this.isServerOnline = true;
                this.fetchMessages();
            } else {
                this.isServerOnline = false;
            }
        } catch (e) {
            this.isServerOnline = false;
        }
    },

    async leave() {
        if (!this.isConnected || !this.nickname) return;
        try {
            await fetch(`${API_BASE}?action=leave`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ nickname: this.nickname }),
                keepalive: true 
            });
        } catch (e) {}
    },

    handleVisibility() {
        if (document.visibilityState === 'visible' && this.isConnected) {
            this.sendHeartbeat();
            this.fetchMessages();
        }
    },

    startPolling() {
        if (this.pollingInterval) return;
        
        // 1. Setup Visibility Listener with saved reference for clean removal
        this._handler = this.handleVisibility.bind(this);
        document.addEventListener('visibilitychange', this._handler);

        // 2. Initial Sync
        this.sendHeartbeat();
        this.fetchMessages();
        this.fetchUsers();
        
        // 3. Main Sync Loop (Messages/Users)
        let lastBackgroundSync = 0;
        this.pollingInterval = setInterval(() => {
            const isVisible = document.visibilityState === 'visible';
            const now = Date.now();

            if (isVisible) {
                // Foreground: Regular 2s sync
                this.fetchMessages();
                this.fetchUsers();
            } else if (now - lastBackgroundSync > 30000) {
                // Background: Slow 30s sync
                this.fetchMessages();
                this.fetchUsers();
                lastBackgroundSync = now;
            }
        }, 2000);

        // 4. Heartbeat Loop (Stays consistent at 10s to prevent timeout)
        this.heartbeatInterval = setInterval(() => this.sendHeartbeat(), 10000);

        // 5. Exit Listener
        this._unloadHandler = () => this.leave();
        window.addEventListener('beforeunload', this._unloadHandler);
    },

    stopPolling() {
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
            this.pollingInterval = null;
        }
        if (this.heartbeatInterval) {
            clearInterval(this.heartbeatInterval);
            this.heartbeatInterval = null;
        }
        if (this._handler) {
            document.removeEventListener('visibilitychange', this._handler);
            this._handler = null;
        }
    },

    async changeNickname(newNick) {
        if (!newNick || newNick.trim().length < 3) {
            this.addMessage({ 
                type: 'system', 
                text: '*** Nickname must be at least 3 characters.',
                localOnly: true 
            });
            return;
        }

        const oldNick = this.nickname;
        const cleanNick = newNick.trim();

        // 1. Leave with old nick
        await this.leave();

        // 2. Update state and persistence
        this.nickname = cleanNick;
        localStorage.setItem('chat_nickname', cleanNick);

        // 3. Confirm with new presence
        await this.sendHeartbeat();
        this.addMessage({ 
            type: 'system', 
            text: `*** Your nickname is now ${cleanNick}`,
            localOnly: true 
        });
    },

    clearHistory() {
        this.messages = [];
    }
});
