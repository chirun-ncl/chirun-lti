import redis.asyncio as redis

def get_cache():
    return redis.Redis()
