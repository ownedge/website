import { reactive } from 'vue';

const isProd = import.meta.env.PROD;
const API_BASE = isProd ? '/api/chat.php' : '/api/chat.php';

export const chatStore = reactive({
    chatNickname: null, // The actual unique nickname used on the network
    isConnected: false,
    showPopup: true,
    userLat: null,
    userLon: null,
    sessionId: null,
    messages: [],
    users: [],
    pollingInterval: null,
    heartbeatInterval: null,
    isServerOnline: true,
    topic: { text: '...', author: '...', modified: '' },
    lastId: 0,
    
    async init() {
        this.lastId = (Date.now() / 1000) - 0.5;
        this.messages = [];
        this.chatNickname = null; // Reset
        
        // Load or Create Session ID
        let sid = localStorage.getItem('chat_session_id');
        if (!sid) {
            sid = 'sess-' + Math.random().toString(36).substr(2, 9) + Date.now().toString(36);
            localStorage.setItem('chat_session_id', sid);
        }
        this.sessionId = sid;

        // 1. Fetch Users RAW (don't filter self) to check for duplicates
        await this.fetchUsers(false);
        
        // 2. Negotiate Unique Nickname
        if (this.nickname) {
            let base = this.nickname;
            let candidate = base;
            let suffix = 2;
            
            // Reclaim strategy: If duplicate exists BUT has same session ID, it's us (ghost). We take it.
            // If duplicate exists has different session ID, it's taken. we rename.
            
            const isTaken = (name) => {
                 return this.users.some(u => 
                    u.nickname.toLowerCase() === name.toLowerCase() && 
                    u.id !== this.sessionId // Only count as taken if ID is diff
                 );
            };

            while (isTaken(candidate)) {
                candidate = `${base}${suffix}`;
                suffix++;
            }
            
            this.chatNickname = candidate;
            
            if (this.chatNickname !== this.nickname) {
                this.addMessage({
                    id: 'sys-rename-' + Date.now(),
                    type: 'system',
                    text: `*** Nickname '${this.nickname}' is taken. You are connected as '${this.chatNickname}'.`,
                    timestamp: new Date().toISOString(),
                    localOnly: true
                });
            }
        }
        
        await Promise.all([
            this.fetchTopic(),
            this.fetchMessages()
        ]);
        this.startPolling();
    },

    // ... (fetchTopic, updateTopic catchup if simplified, but I'll skip to fetchUsers) ...
    // Note: I can't skip lines easily with replace_file_content if they aren't contiguous context.
    // I will target the Blocks I need.
    // Actually, init is contiguous with state definition at top.
    // fetchUsers is further down.
    // I will break this into 2 edits or use replace_file_content carefully.
    // Let's do STATE + INIT first.
    
    // ... wait, I'll use separate replacementChunks if using multi_replace?
    // The tool is `replace_file_content` (single block). I should use `multi_replace_file_content` if needed.
    // Agent has `multi_replace_file_content`.
    // I will use `replace_file_content` for the whole file? No, risky.
    // I will use `multi_replace_file_content`.

    // First Chunk: State + Init (Start of file)
    // Second Chunk: fetchUsers + sendHeartbeat (Middle)
    // Third Chunk: changeNickname (End)
    
    // Wait, let me look at `fetchTopic` position.
    // Lines 59-77.
    // `state` is 6-17.
    // `init` is 18-57.
    // `fetchUsers` is 123-146.
    // `sendHeartbeat` is 148-158.
    // `changeNickname` is 275-318.
    
    // I'll use multi_replace.


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
                body: JSON.stringify({ topic: newText, user: this.chatNickname || this.nickname })
            });
            if (response.ok) {
                const data = await response.json();
                this.topic = { 
                    text: data.topic, 
                    author: data.author || (this.chatNickname || this.nickname), 
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
                    const newMsgs = data.filter(m => !this.messages.find(existing => existing.id === m.id));
                    if (newMsgs.length > 0) {
                        this.messages.push(...newMsgs);
                        this.lastId = data[data.length - 1].id;

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

    async fetchUsers(filterSelf = true) {
        try {
            const response = await fetch(`${API_BASE}?action=users`);
            if (response.ok) {
                const data = await response.json();
                if (Array.isArray(data)) {
                    // Normalize to objects
                    const normalized = data.map(u => {
                        if (typeof u === 'string') return { nickname: u, lat: null, lon: null };
                        return u; 
                    });

                    if (filterSelf) {
                        // Filter out our OWN nickname (whether preferred or effective)
                        const myNick = this.chatNickname || this.nickname;
                        this.users = normalized.filter(u => u.nickname !== myNick);
                    } else {
                        this.users = normalized;
                    }
                } else {
                    console.error("fetchUsers: Expected array, got:", data);
                }
            } else {
                const body = await response.text();
                // console.error(`fetchUsers Failed (${response.status}):`, body);
            }
        } catch (e) {
            console.error("fetchUsers Error:", e);
        }
    },

    async sendHeartbeat() {
        if (!this.isConnected || !this.nickname) return;
        const activeNick = this.chatNickname || this.nickname;
        
        const payload = { nickname: activeNick, id: this.sessionId };
        if (this.userLat) payload.lat = this.userLat;
        if (this.userLon) payload.lon = this.userLon;

        try {
            await fetch(`${API_BASE}?action=presence`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
        } catch (e) {}
    },

    async addMessage(msg) {
        const activeNick = this.chatNickname || this.nickname;
        const localId = 'temp-' + Date.now() + Math.random();
        const localMsg = { 
            ...msg, 
            id: localId, 
            user: msg.user || activeNick,
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
                
                const alreadySynced = this.messages.find(m => m.id === verifiedMsg.id);
                const tempIdx = this.messages.findIndex(m => m.id === localId);
                
                if (alreadySynced) {
                    if (tempIdx !== -1) this.messages.splice(tempIdx, 1);
                } else {
                    if (tempIdx !== -1) {
                        this.messages[tempIdx].id = verifiedMsg.id;
                        this.messages[tempIdx].timestamp = verifiedMsg.timestamp;
                    }
                }
                
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
        const activeNick = this.chatNickname || this.nickname;
        try {
            await fetch(`${API_BASE}?action=leave`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ nickname: activeNick }),
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
        
        this._handler = this.handleVisibility.bind(this);
        document.addEventListener('visibilitychange', this._handler);

        this.sendHeartbeat();
        this.fetchMessages();
        this.fetchUsers();
        
        let lastBackgroundSync = 0;
        this.pollingInterval = setInterval(() => {
            const isVisible = document.visibilityState === 'visible';
            const now = Date.now();

            if (isVisible) {
                this.fetchMessages();
                this.fetchUsers();
            } else if (now - lastBackgroundSync > 30000) {
                this.fetchMessages();
                this.fetchUsers();
                lastBackgroundSync = now;
            }
        }, 2000);

        this.heartbeatInterval = setInterval(() => this.sendHeartbeat(), 10000);

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

        const cleanNick = newNick.trim();
        
        // 1. Leave with old nick (using current chatNickname)
        await this.leave();

        // 2. Update persistence
        this.nickname = cleanNick;
        localStorage.setItem('chat_nickname', cleanNick);
        this.chatNickname = null; // Reset effective nick

        // 3. Negotiate unique nickname again (re-using logic from init)
        await this.fetchUsers(false);
        let candidate = cleanNick;
        let suffix = 2;
        
        const isTaken = (name) => {
             return this.users.some(u => 
                u.nickname.toLowerCase() === name.toLowerCase() && 
                u.id !== this.sessionId 
             );
        };

        while (isTaken(candidate)) {
            candidate = `${cleanNick}${suffix}`;
            suffix++;
        }
        this.chatNickname = candidate;

        // 4. Confirm with new presence
        await this.sendHeartbeat();
        
        let msg = `*** Your nickname is now ${this.chatNickname}`;
        if (this.chatNickname !== cleanNick) {
            msg += ` (original '${cleanNick}' was taken)`;
        }
        
        this.addMessage({ 
            type: 'system', 
            text: msg,
            localOnly: true 
        });
    },

    clearHistory() {
        this.messages = [];
    }
});
