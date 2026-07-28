import { AgentClient } from "@21st-sdk/node"
import { z } from "zod"

const command = process.argv[2]
const payloadRaw = process.argv[3] ?? "{}"

const payload = JSON.parse(payloadRaw)
const apiKey = process.env.API_KEY_21ST

if (!apiKey) {
  console.error("API_KEY_21ST is not configured")
  process.exit(1)
}

const client = new AgentClient({ apiKey })

const tokenSchema = z.object({
  agent: z.string().min(1),
  userId: z.string().min(1),
  expiresIn: z.string().min(1).optional(),
})

const createSessionSchema = z.object({
  agent: z.string().min(1),
  userId: z.string().min(1),
  name: z.string().min(1).optional(),
})

const createThreadSchema = z.object({
  sandboxId: z.string().min(1),
  name: z.string().min(1).optional(),
})

try {
  switch (command) {
    case "create-token": {
      const input = tokenSchema.parse(payload)
      const result = await client.tokens.create(input)
      console.log(JSON.stringify(result))
      break
    }

    case "create-session": {
      const input = createSessionSchema.parse(payload)
      const sandbox = await client.sandboxes.create({
        agent: input.agent,
      })
      const thread = await client.threads.create({
        sandboxId: sandbox.id,
        name: input.name ?? "Frontend Chat",
      })

      console.log(JSON.stringify({
        sandboxId: sandbox.id,
        threadId: thread.id,
      }))
      break
    }

    case "create-thread": {
      const input = createThreadSchema.parse(payload)
      const thread = await client.threads.create({
        sandboxId: input.sandboxId,
        name: input.name ?? "Frontend Chat",
      })

      console.log(JSON.stringify({
        threadId: thread.id,
      }))
      break
    }

    default:
      console.error(`Unsupported bridge command: ${command ?? "<missing>"}`)
      process.exit(1)
  }
} catch (error) {
  const message = error instanceof Error ? error.message : "Unknown 21st bridge error"
  console.error(message)
  process.exit(1)
}