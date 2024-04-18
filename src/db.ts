import { Db, MongoClient, ObjectId } from 'mongodb';
import * as assert from 'assert';

import config from './config';

const url = `mongodb://${config.mongoHost}:27017`;
const dbname = 'bitcoin-faucet';

export declare type Mixed = string | number | Buffer | boolean;
export declare type MixedData = {
    [key: string]: Mixed;
};
export declare type DBQuery = {
    [key: string]: Mixed | {
        $gt: number
    }
}

export class DB {
    connected: boolean = false;
    client?: MongoClient;
    db?: Db;

    id(id: string) {
        return new ObjectId(id);
    }
    async connect() {
        assert(!this.connected);
        this.client = await MongoClient.connect(url);
        this.db = this.client.db(dbname);
        this.connected = true;
    }
    disconnect() {
        assert(this.connected);
        this.client!.close();
        this.connected = false;
        this.db = undefined;
    }
    async insert(coll: string, obj: MixedData) {
        return await this.db!.collection(coll).insertOne(obj);
    }

    async find(coll: string, query: DBQuery) {
        const cursor = this.db!.collection(coll).find(query);
        return await cursor.toArray();
    }
    async update(coll: string, query: MixedData, set: MixedData) {
        return await this.db!.collection(coll).updateOne(
            query,
            {
                $set: set,
                $currentDate: { lastModified: new Date(Date.now()) },
            }
        );
    }
    async upsert(coll: string, query: MixedData, set: MixedData) {
        return await this.db!.collection(coll).updateOne(
            query,
            {
                $set: set,
                $currentDate: { lastModified: new Date(Date.now()) },
            },
            {
                upsert: true,
            }
        );
    }
    async remove(coll: string, query: MixedData) {
        return await this.db!.collection(coll).deleteOne(query);
    }
    async removeAll(coll: string, query: MixedData) {
        return await this.db!.collection(coll).deleteMany(query);
    }
};

export const db = new DB();
